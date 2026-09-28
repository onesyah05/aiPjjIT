<?php

namespace App\Jobs;

use App\Models\AiCredential;
use App\Models\Conversation;
use App\Models\DiscordBotConversation;
use App\Models\User;
use App\Services\AI\GeminiService;
use App\Services\CredentialPoolService;
use App\Services\Knowledge\KnowledgeLinkExtractor;
use App\Services\Knowledge\RetrievalService;
use App\Services\PromptBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HandleDiscordMention implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public readonly string $channelId,
        public readonly string $messageId,
        public readonly string $userId,
        public readonly string $username,
        public readonly string $question,
    ) {}

    public function handle(
        GeminiService $gemini,
        CredentialPoolService $credentialPool,
        PromptBuilderService $promptBuilder,
        RetrievalService $retrievalService,
        KnowledgeLinkExtractor $linkExtractor,
    ): void {
        if (DiscordBotConversation::where('discord_message_id', $this->messageId)->exists()) {
            return;
        }

        $record = DiscordBotConversation::create([
            'discord_channel_id' => $this->channelId,
            'discord_user_id' => $this->userId,
            'discord_message_id' => $this->messageId,
            'discord_username' => $this->username,
            'question' => $this->question,
            'status' => 'pending',
        ]);

        $this->sendTyping();

        $placeholderId = $this->replyToDiscord('Memproses jawaban... ⏳');
        $lastEditTime = microtime(true);

        $startedAt = hrtime(true);

        try {
            $systemUser = User::query()
                ->where('email', 'discord-bot@pjj.ai')
                ->first() ?? User::query()->oldest('id')->firstOrFail();
            $conversation = new Conversation(['course_id' => null, 'user_id' => $systemUser->id]);
            $retrieved = $retrievalService->retrieve($systemUser, $conversation, $this->question);
            $sourceLinks = $linkExtractor->extractFromResults($retrieved->all(), $this->question);

            $prompt = $promptBuilder->build(
                $this->question,
                $retrieved->map(fn (array $r): array => [
                    'content' => $r['content'],
                    'score' => $r['score'],
                    'links' => $linkExtractor->extract($r['chunk']->version?->content ?? $r['content']),
                ])->all(),
                'general',
                [],
            );

            Log::info('DISCORD_PROMPT_DEBUG', [
                'channel' => $this->channelId,
                'retrieved_count' => $retrieved->count(),
                'prompt_length' => strlen($prompt),
                'prompt' => $prompt,
            ]);

            $credentials = $credentialPool->getAvailableCredentials(
                (int) config('services.gemini.max_attempts', 6),
            );

            if ($credentials->isEmpty()) {
                $record->update(['status' => 'failed', 'error_code' => 'credentials_unavailable']);
                $msg = '⚠️ Semua credential AI sedang tidak tersedia. Coba lagi nanti.';
                $placeholderId ? $this->editDiscordMessage($placeholderId, $msg) : $this->replyToDiscord($msg);

                return;
            }

            $answer = $this->tryGenerateAnswer($gemini, $credentialPool, $credentials, $prompt, function (string $partial) use ($placeholderId, &$lastEditTime) {
                if ($placeholderId && microtime(true) - $lastEditTime > 1.5) {
                    $this->editDiscordMessage($placeholderId, mb_substr("<@{$this->userId}>\n\n".$partial, 0, 1950).' ⏳');
                    $lastEditTime = microtime(true);
                }
            });

            if ($answer === null) {
                $record->update(['status' => 'failed', 'error_code' => 'generation_failed']);
                $msg = '⚠️ Gagal mendapatkan jawaban dari AI. Silakan coba lagi.';
                $placeholderId ? $this->editDiscordMessage($placeholderId, $msg) : $this->replyToDiscord($msg);

                return;
            }

            $answer .= $linkExtractor->appendixFor($answer, $sourceLinks);

            $latency = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $record->update(['status' => 'completed', 'answer' => $answer, 'latency_ms' => $latency]);

            preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $answer, $imageMatches, PREG_SET_ORDER);

            $textWithoutImages = trim(preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '', $answer));

            $fullReply = "<@{$this->userId}>\n\n".$textWithoutImages;

            $textChunks = mb_str_split($fullReply, 1950);

            if ($placeholderId) {
                $this->editDiscordMessage($placeholderId, $textChunks[0]);
                unset($textChunks[0]);
            }

            foreach ($textChunks as $chunk) {
                $this->replyToDiscord($chunk);
            }

            foreach ($imageMatches as $match) {
                $imageUrl = $match[2];
                $this->replyToDiscord($imageUrl);
            }

        } catch (Throwable $e) {
            Log::error('HandleDiscordMention failed', ['error' => $e->getMessage(), 'channel' => $this->channelId]);
            $record->update(['status' => 'failed', 'error_code' => 'exception']);
            $msg = '⚠️ Terjadi kesalahan saat memproses pertanyaanmu. Coba lagi nanti.';
            isset($placeholderId) && $placeholderId ? $this->editDiscordMessage($placeholderId, $msg) : $this->replyToDiscord($msg);
        }
    }

    /**
     * @param  Collection<int, AiCredential>  $credentials
     */
    private function tryGenerateAnswer(
        GeminiService $gemini,
        CredentialPoolService $credentialPool,
        Collection $credentials,
        string $prompt,
        ?callable $onProgress = null,
    ): ?string {
        foreach ($credentials as $credential) {
            $credentialPool->recordAttempt($credential);

            try {
                $content = '';
                foreach ($gemini->stream($credential->encrypted_secret, $prompt) as $chunk) {
                    $content .= $chunk;
                    if ($onProgress) {
                        $onProgress($content);
                    }
                }

                if ($content !== '') {
                    $credentialPool->recordSuccess($credential);

                    return $content;
                }
            } catch (RequestException $e) {
                $status = $e->response->status();
                $errorCode = $status === 429 ? 'rate_limited' : "http_{$status}";
                $credentialPool->recordFailure($credential, $errorCode);

                if ($status === 429) {
                    $credentialPool->markRateLimitHit($credential);
                } elseif ($status === 503) {
                    $credential->update(['cooldown_until' => now()->addSeconds(30), 'last_error_code' => 'http_503']);
                } elseif (in_array($status, [401, 403], true)) {
                    $credentialPool->markInvalid($credential, $errorCode, 'Credential ditolak Gemini.');
                }
            } catch (Throwable) {
                $credentialPool->recordFailure($credential, 'provider_error');
            }
        }

        return null;
    }

    private function sendTyping(): void
    {
        try {
            Http::withHeaders(['Authorization' => 'Bot '.config('services.discord.bot_token')])
                ->post("https://discord.com/api/v10/channels/{$this->channelId}/typing");
        } catch (Throwable) {
            // Discord typing failures must not stop answer generation.
        }
    }

    private function replyToDiscord(string $content): ?string
    {
        $token = config('services.discord.bot_token');

        $response = Http::withHeaders(['Authorization' => "Bot {$token}"])
            ->post("https://discord.com/api/v10/channels/{$this->channelId}/messages", [
                'content' => $content,
                'message_reference' => ['message_id' => $this->messageId],
            ]);

        return $response->json('id');
    }

    private function editDiscordMessage(string $msgId, string $content): void
    {
        $token = config('services.discord.bot_token');
        try {
            Http::withHeaders(['Authorization' => "Bot {$token}"])
                ->patch("https://discord.com/api/v10/channels/{$this->channelId}/messages/{$msgId}", [
                    'content' => $content,
                ]);
        } catch (Throwable) {
            // A failed progress edit must not fail the completed answer.
        }
    }
}

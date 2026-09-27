<?php

namespace App\Jobs;

use App\Models\AiCredential;
use App\Models\DiscordBotConversation;
use App\Models\KnowledgeChunk;
use App\Services\AI\GeminiService;
use App\Services\CredentialPoolService;
use App\Services\PromptBuilderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    ): void {
        // Idempotency: skip if already processed
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

        // Typing indicator
        $this->sendTyping();

        $startedAt = hrtime(true);

        try {
            $retrieved = $this->retrieveKnowledge($this->question);

            $prompt = $promptBuilder->build(
                $this->question,
                $retrieved->map(fn (array $r): array => [
                    'content' => $r['content'],
                    'score' => $r['score'],
                    'links' => [],
                ])->all(),
                'general',
                [],
            );

            $credentials = $credentialPool->getAvailableCredentials(
                (int) config('services.gemini.max_attempts', 6),
            );

            if ($credentials->isEmpty()) {
                $record->update(['status' => 'failed', 'error_code' => 'credentials_unavailable']);
                $this->replyToDiscord('⚠️ Semua credential AI sedang tidak tersedia. Coba lagi nanti.');

                return;
            }

            $answer = $this->tryGenerateAnswer($gemini, $credentialPool, $credentials, $prompt);

            if ($answer === null) {
                $record->update(['status' => 'failed', 'error_code' => 'generation_failed']);
                $this->replyToDiscord('⚠️ Gagal mendapatkan jawaban dari AI. Silakan coba lagi.');

                return;
            }

            $latency = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $record->update(['status' => 'completed', 'answer' => $answer, 'latency_ms' => $latency]);

            // 1. Extract images: ![alt](url)
            preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $answer, $imageMatches, PREG_SET_ORDER);

            // 2. Remove images from main text
            $textWithoutImages = trim(preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '', $answer));

            // 3. Prepend mention
            $fullReply = "<@{$this->userId}>\n\n".$textWithoutImages;

            // 4. Split message if it's too long (> 1950 chars)
            $textChunks = mb_str_split($fullReply, 1950);
            foreach ($textChunks as $chunk) {
                $this->replyToDiscord($chunk);
            }

            // 5. Send images separately
            foreach ($imageMatches as $match) {
                $imageUrl = $match[2];
                $this->replyToDiscord($imageUrl);
            }

        } catch (Throwable $e) {
            Log::error('HandleDiscordMention failed', ['error' => $e->getMessage(), 'channel' => $this->channelId]);
            $record->update(['status' => 'failed', 'error_code' => 'exception']);
            $this->replyToDiscord('⚠️ Terjadi kesalahan saat memproses pertanyaanmu. Coba lagi nanti.');
        }
    }

    /** @return Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}> */
    private function retrieveKnowledge(string $question): Collection
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', Str::lower($question)) ?: [])
            ->filter(fn (string $t): bool => mb_strlen($t) >= 3)
            ->unique()
            ->values();

        if ($terms->isEmpty()) {
            return collect();
        }

        return KnowledgeChunk::query()
            ->select('knowledge_chunks.*')
            ->join('knowledge_versions', 'knowledge_versions.id', '=', 'knowledge_chunks.knowledge_version_id')
            ->join('knowledges', 'knowledges.id', '=', 'knowledge_versions.knowledge_id')
            ->whereNull('knowledges.deleted_at')
            ->whereColumn('knowledges.active_version_id', 'knowledge_versions.id')
            ->where('knowledge_versions.status', 'approved')
            ->where('knowledge_versions.processing_status', 'ready')
            ->whereIn('knowledges.visibility', ['community', 'course'])
            ->where('knowledges.status', 'approved')
            ->limit(200)
            ->get()
            ->map(function (KnowledgeChunk $chunk) use ($terms): array {
                $content = Str::lower($chunk->content);
                $matches = $terms->filter(fn (string $t): bool => str_contains($content, $t))->count();

                return ['chunk' => $chunk, 'content' => $chunk->content, 'score' => $matches / $terms->count()];
            })
            ->filter(fn (array $r): bool => $r['score'] > 0)
            ->sortByDesc('score')
            ->take(5)
            ->values();
    }

    /**
     * @param  Collection<int, AiCredential>  $credentials
     */
    private function tryGenerateAnswer(
        GeminiService $gemini,
        CredentialPoolService $credentialPool,
        Collection $credentials,
        string $prompt,
    ): ?string {
        foreach ($credentials as $credential) {
            $credentialPool->recordAttempt($credential);

            try {
                $content = '';
                foreach ($gemini->stream($credential->encrypted_secret, $prompt) as $chunk) {
                    $content .= $chunk;
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
            // Non-critical — ignore
        }
    }

    private function replyToDiscord(string $content): void
    {
        $token = config('services.discord.bot_token');

        Http::withHeaders(['Authorization' => "Bot {$token}"])
            ->post("https://discord.com/api/v10/channels/{$this->channelId}/messages", [
                'content' => $content,
                'message_reference' => ['message_id' => $this->messageId],
            ]);
    }
}

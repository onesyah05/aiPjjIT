<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\DiscordAccount;
use App\Models\DiscordBotConversation;
use App\Models\User;
use App\Services\Chat\ChatService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class HandleDiscordMention implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    /** @return array<int, int> */
    public function backoff(): array
    {
        // Gemini overload (HTTP 503) spikes are usually temporary; wait them out.
        return [30, 60, 120];
    }

    public function __construct(
        public readonly string $channelId,
        public readonly string $messageId,
        public readonly string $userId,
        public readonly string $username,
        public readonly string $question,
    ) {
        $this->onQueue('discord');
    }

    public function handle(ChatService $chatService): void
    {
        $record = DiscordBotConversation::query()
            ->where('discord_message_id', $this->messageId)
            ->first();

        if ($record?->status === 'completed') {
            return;
        }

        $record ??= new DiscordBotConversation;
        $record->fill([
            'discord_channel_id' => $this->channelId,
            'discord_user_id' => $this->userId,
            'discord_message_id' => $this->messageId,
            'discord_username' => $this->username,
            'question' => $this->question,
            'answer' => null,
            'status' => 'pending',
            'error_code' => null,
            'latency_ms' => null,
        ])->save();

        $this->sendTyping();

        $placeholderId = $this->replyToDiscord('Memproses jawaban... ⏳');
        $lastEditTime = microtime(true);

        $startedAt = hrtime(true);

        try {
            $user = DiscordAccount::query()
                ->with('user')
                ->where('discord_user_id', $this->userId)
                ->first()?->user;
            $user ??= User::query()
                ->where('email', 'discord-bot@pjj.ai')
                ->first() ?? User::query()->oldest('id')->firstOrFail();
            $conversation = Conversation::query()->create([
                'user_id' => $user->id,
                'mode' => 'general',
                'status' => 'active',
                'title' => '[Discord] '.mb_substr($this->question, 0, 70),
            ]);
            $answer = '';

            foreach ($chatService->stream($conversation, $this->question, "discord:{$this->messageId}") as $event) {
                $answer .= (string) ($event['content'] ?? '');

                if ($placeholderId && $answer !== '' && microtime(true) - $lastEditTime > 1.5) {
                    $this->editDiscordMessage($placeholderId, mb_substr("<@{$this->userId}>\n\n".$answer, 0, 1950).' ⏳');
                    $lastEditTime = microtime(true);
                }
            }

            if ($answer === '') {
                throw new \RuntimeException('Web chat service returned an empty answer.');
            }

            $latency = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $record->update(['status' => 'completed', 'answer' => $answer, 'latency_ms' => $latency]);

            preg_match_all('/!\[([^\]]*)\]\(([^)]+)\)/', $answer, $imageMatches, PREG_SET_ORDER);

            $textWithoutImages = trim(preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '', $answer));

            $fullReply = "<@{$this->userId}>\n\n".$textWithoutImages;

            $textChunks = mb_str_split($fullReply, 1950);

            if ($placeholderId && $this->editDiscordMessage($placeholderId, $textChunks[0])) {
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

            throw $e;
        }
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
            ])
            ->throw();

        return $response->json('id');
    }

    private function editDiscordMessage(string $msgId, string $content): bool
    {
        $token = config('services.discord.bot_token');

        try {
            return Http::withHeaders(['Authorization' => "Bot {$token}"])
                ->patch("https://discord.com/api/v10/channels/{$this->channelId}/messages/{$msgId}", [
                    'content' => $content,
                ])
                ->successful();
        } catch (Throwable $exception) {
            Log::warning('Discord message edit failed', [
                'message_id' => $msgId,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}

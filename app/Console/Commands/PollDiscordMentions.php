<?php

namespace App\Console\Commands;

use App\Jobs\HandleDiscordMention;
use App\Models\DiscordBotConversation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollDiscordMentions extends Command
{
    protected $signature = 'discord:poll-mentions
                            {--daemon : Run as a long-lived daemon, polling continuously}
                            {--sleep=2 : Seconds to sleep between polls (daemon mode)}';

    protected $description = 'Poll Discord channel for bot mentions and dispatch AI reply jobs';

    private const BOT_ID = '1545329070315802675';

    public function handle(): int
    {
        $daemon = (bool) $this->option('daemon');
        $sleep = max(1, (int) $this->option('sleep'));

        if ($daemon) {
            $this->info('Starting Discord mention daemon (Ctrl+C to stop)…');
            $this->runDaemon($sleep);

            return self::SUCCESS;
        }

        return $this->pollOnce();
    }

    private function runDaemon(int $sleep): void
    {
        while (true) {
            try {
                $this->pollOnce();
            } catch (\Throwable $e) {
                Log::error('Discord daemon poll error', ['error' => $e->getMessage()]);
            }

            sleep($sleep);
        }
    }

    private function pollOnce(): int
    {
        $token = config('services.discord.bot_token');
        $channelId = config('services.discord.bot_channel_id', '1552943987428425828');

        if (! $token) {
            $this->error('DISCORD_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        $cacheKey = "discord_poll_last_message_{$channelId}";
        $lastMessageId = Cache::get($cacheKey);

        $query = ['limit' => 20];

        if ($lastMessageId) {
            $query['after'] = $lastMessageId;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bot {$token}",
        ])->get("https://discord.com/api/v10/channels/{$channelId}/messages", $query);

        if (! $response->successful()) {
            Log::error('Discord poll failed', ['status' => $response->status(), 'body' => $response->body()]);

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $messages */
        $messages = $response->json();

        if (empty($messages)) {
            return self::SUCCESS;
        }

        // Discord returns newest-first; process oldest-first
        $messages = array_reverse($messages);

        foreach ($messages as $message) {
            $messageId = (string) ($message['id'] ?? '');
            $authorId = (string) ($message['author']['id'] ?? '');
            $username = (string) ($message['author']['username'] ?? 'Unknown');
            $content = (string) ($message['content'] ?? '');
            $isBot = (bool) ($message['author']['bot'] ?? false);

            // Always advance the cursor
            Cache::forever($cacheKey, $messageId);

            if ($isBot || $authorId === self::BOT_ID) {
                continue;
            }

            $mentions = collect($message['mentions'] ?? []);
            $botMentioned = $mentions->contains('id', self::BOT_ID);

            if (! $botMentioned) {
                continue;
            }

            $question = trim(preg_replace('/<@!?'.self::BOT_ID.'>/', '', $content) ?? $content);

            if ($question === '') {
                continue;
            }

            if (DiscordBotConversation::where('discord_message_id', $messageId)->exists()) {
                continue;
            }

            $this->info("[{$username}] → ".mb_substr($question, 0, 80));

            HandleDiscordMention::dispatch(
                channelId: $channelId,
                messageId: $messageId,
                userId: $authorId,
                username: $username,
                question: $question,
            );
        }

        return self::SUCCESS;
    }
}

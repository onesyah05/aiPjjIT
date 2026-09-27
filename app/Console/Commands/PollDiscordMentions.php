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
    protected $signature = 'discord:poll-mentions';

    protected $description = 'Poll Discord channel for bot mentions and dispatch AI reply jobs';

    private const BOT_ID = '1545329070315802675';

    public function handle(): int
    {
        $token = config('services.discord.bot_token');
        $channelId = config('services.discord.bot_channel_id', '1552943987428425828');

        if (! $token) {
            $this->error('DISCORD_BOT_TOKEN is not configured.');

            return self::FAILURE;
        }

        // Remember the last processed message ID to avoid re-processing
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
            $this->error('Failed to fetch messages: '.$response->status());

            return self::FAILURE;
        }

        /** @var array<int, array<string, mixed>> $messages */
        $messages = $response->json();

        if (empty($messages)) {
            $this->line('No new messages.');

            return self::SUCCESS;
        }

        // Messages come newest-first; process oldest-first
        $messages = array_reverse($messages);

        $dispatched = 0;

        foreach ($messages as $message) {
            $messageId = (string) ($message['id'] ?? '');
            $authorId = (string) ($message['author']['id'] ?? '');
            $username = (string) ($message['author']['username'] ?? 'Unknown');
            $content = (string) ($message['content'] ?? '');
            $isBot = (bool) ($message['author']['bot'] ?? false);

            // Update cursor even for messages we skip
            Cache::forever($cacheKey, $messageId);

            // Skip bot messages
            if ($isBot || $authorId === self::BOT_ID) {
                continue;
            }

            // Only act when our bot is mentioned
            $mentions = collect($message['mentions'] ?? []);
            $botMentioned = $mentions->contains('id', self::BOT_ID);

            if (! $botMentioned) {
                continue;
            }

            // Strip the bot mention from the question
            $question = trim(preg_replace('/<@!?'.self::BOT_ID.'>/', '', $content) ?? $content);

            if ($question === '') {
                continue;
            }

            // Skip if already processed (idempotency)
            if (DiscordBotConversation::where('discord_message_id', $messageId)->exists()) {
                continue;
            }

            $this->info("Dispatching job for mention by {$username}: ".mb_substr($question, 0, 60));

            HandleDiscordMention::dispatch(
                channelId: $channelId,
                messageId: $messageId,
                userId: $authorId,
                username: $username,
                question: $question,
            );

            $dispatched++;
        }

        $this->info('Processed '.count($messages)." message(s), dispatched {$dispatched} job(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Http\Controllers;

use App\Jobs\HandleDiscordMention;
use App\Jobs\IndexDiscordMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DiscordWebhookController extends Controller
{
    /**
     * The bot user ID (SIBERMU ASIST).
     */
    private const BOT_ID = '1545329070315802675';

    /**
     * Only respond to mentions in this specific channel.
     */
    private const ALLOWED_CHANNEL_ID = '1552943987428425828';

    public function handle(Request $request): JsonResponse
    {
        // Verify the secret token to prevent unauthorized calls
        $secret = config('services.discord.webhook_secret');

        if ($secret && $request->header('X-Discord-Webhook-Secret') !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();
        $type = $payload['type'] ?? null;

        // Discord sends a PING on webhook registration
        if ($type === 'PING' || ($payload['event_type'] ?? null) === 'PING') {
            return response()->json(['type' => 1]);
        }

        // Handle MESSAGE_CREATE events
        $event = $payload['event'] ?? $payload;
        if (($event['t'] ?? $payload['t'] ?? null) !== 'MESSAGE_CREATE') {
            // Also handle raw dispatch format
            if (! isset($payload['content'])) {
                return response()->json(['ok' => true]);
            }
            $event = $payload;
        }

        $data = $event['d'] ?? $event;

        $channelId = (string) ($data['channel_id'] ?? '');
        $messageId = (string) ($data['id'] ?? '');
        $authorId = (string) ($data['author']['id'] ?? '');
        $username = (string) ($data['author']['username'] ?? 'Unknown');
        $content = (string) ($data['content'] ?? '');
        $isBot = (bool) ($data['author']['bot'] ?? false);

        // Ignore messages from bots (including self)
        if ($isBot || $authorId === self::BOT_ID) {
            return response()->json(['ok' => true]);
        }

        // Realtime knowledge indexing: index every human message from configured channels
        $this->indexIfKnowledgeChannel($channelId, $username, $content, $data);

        // Only respond to mentions in the designated channel
        if ($channelId !== self::ALLOWED_CHANNEL_ID) {
            return response()->json(['ok' => true]);
        }

        // Only respond when the bot is mentioned
        $mentions = collect($data['mentions'] ?? []);
        $botMentioned = $mentions->contains('id', self::BOT_ID);

        if (! $botMentioned) {
            return response()->json(['ok' => true]);
        }

        // Strip the bot mention from the question text
        $question = trim(preg_replace('/<@!?'.self::BOT_ID.'>/', '', $content) ?? $content);

        if ($question === '') {
            return response()->json(['ok' => true]);
        }

        Log::info('Discord bot mention received', [
            'channel' => $channelId,
            'user' => $username,
            'question' => mb_substr($question, 0, 100),
        ]);

        HandleDiscordMention::dispatch(
            channelId: $channelId,
            messageId: $messageId,
            userId: $authorId,
            username: $username,
            question: $question,
        );

        return response()->json(['ok' => true]);
    }

    /**
     * If the message comes from one of the configured knowledge channels (or a thread
     * parented to one), dispatch a job to index it into the knowledge base immediately.
     */
    private function indexIfKnowledgeChannel(string $channelId, string $username, string $content, array $data): void
    {
        $knowledgeChannels = config('services.discord.channel_ids', []);

        if (empty($knowledgeChannels)) {
            return;
        }

        // Match if the message channel or its parent (for threads) is a configured knowledge channel
        $parentId = (string) ($data['parent_id'] ?? '');
        $isKnowledgeChannel = in_array($channelId, $knowledgeChannels, true)
            || ($parentId !== '' && in_array($parentId, $knowledgeChannels, true));

        if (! $isKnowledgeChannel) {
            return;
        }

        IndexDiscordMessage::dispatch(
            channelId: $channelId,
            authorUsername: $username,
            messageContent: $content,
            attachments: $data['attachments'] ?? [],
            embeds: $data['embeds'] ?? [],
        );
    }
}

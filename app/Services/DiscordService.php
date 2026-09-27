<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DiscordService
{
    protected string $token;
    protected string $baseUrl = 'https://discord.com/api/v10';

    public function __construct()
    {
        $this->token = config('services.discord.bot_token');
    }

    /**
     * Get channel information
     */
    public function getChannel(string $channelId): ?array
    {
        if (!$this->token) {
            return null;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $this->token,
        ])->get("{$this->baseUrl}/channels/{$channelId}");

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Failed to fetch Discord channel {$channelId}: " . $response->body());
        return null;
    }

    /**
     * Get messages from a specific channel or thread
     */
    public function getChannelMessages(string $channelId, int $limit = 500): array
    {
        if (!$this->token) {
            Log::warning('Discord Bot Token is not configured.');
            return [];
        }

        $allMessages = [];
        $lastMessageId = null;

        while (count($allMessages) < $limit) {
            $fetchLimit = min(100, $limit - count($allMessages));
            $query = ['limit' => $fetchLimit];
            
            if ($lastMessageId) {
                $query['before'] = $lastMessageId;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bot ' . $this->token,
            ])->get("{$this->baseUrl}/channels/{$channelId}/messages", $query);

            if (!$response->successful()) {
                Log::error("Failed to fetch Discord messages for channel {$channelId}: " . $response->body());
                break;
            }

            $messages = $response->json();
            if (empty($messages)) {
                break;
            }

            $allMessages = array_merge($allMessages, $messages);
            $lastMessageId = end($messages)['id'];
        }

        return $allMessages;
    }

    /**
     * Get active threads in a guild (useful for forum channels)
     */
    public function getActiveThreads(string $guildId): array
    {
        if (!$this->token) {
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $this->token,
        ])->get("{$this->baseUrl}/guilds/{$guildId}/threads/active");

        if ($response->successful()) {
            return $response->json()['threads'] ?? [];
        }

        Log::error("Failed to fetch Discord active threads for guild {$guildId}: " . $response->body());
        return [];
    }

    /**
     * Get archived threads for a channel
     */
    public function getArchivedThreads(string $channelId): array
    {
        if (!$this->token) {
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $this->token,
        ])->get("{$this->baseUrl}/channels/{$channelId}/threads/archived/public");

        if ($response->successful()) {
            return $response->json()['threads'] ?? [];
        }

        Log::error("Failed to fetch Discord archived threads for channel {$channelId}: " . $response->body());
        return [];
    }
}

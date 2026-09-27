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
    public function getChannelMessages(string $channelId, int $limit = 50): array
    {
        if (!$this->token) {
            Log::warning('Discord Bot Token is not configured.');
            return [];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . $this->token,
        ])->get("{$this->baseUrl}/channels/{$channelId}/messages", [
            'limit' => $limit,
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error("Failed to fetch Discord messages for channel {$channelId}: " . $response->body());
        return [];
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

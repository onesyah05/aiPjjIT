<?php

namespace App\Jobs;

use App\Models\DonationLeaderboard;
use App\Services\DiscordDonationLeaderboard;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshDonationLeaderboard implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $channelId)
    {
        $this->onQueue('discord');
    }

    public function handle(DiscordDonationLeaderboard $leaderboard): void
    {
        $board = DonationLeaderboard::query()->where('channel_id', $this->channelId)->first();
        if ($board !== null) {
            $leaderboard->refresh($board);
        }
    }
}

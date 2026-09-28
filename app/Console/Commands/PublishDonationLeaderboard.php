<?php

namespace App\Console\Commands;

use App\Services\DiscordDonationLeaderboard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('donations:publish-leaderboard')]
#[Description('Publish or refresh the Discord donation leaderboard')]
class PublishDonationLeaderboard extends Command
{
    public function handle(DiscordDonationLeaderboard $leaderboard): int
    {
        if (! filled(config('services.pakasir.slug')) || ! filled(config('services.pakasir.api_key')) || ! filled(config('services.pakasir.webhook_secret')) || ! filled(config('services.discord.public_key'))) {
            $this->error('Configure Pakasir credentials and Discord public key before publishing.');

            return self::FAILURE;
        }

        $board = $leaderboard->publish();
        $this->info('Leaderboard message: '.$board->message_id);

        return self::SUCCESS;
    }
}

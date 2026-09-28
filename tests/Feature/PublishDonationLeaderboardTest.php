<?php

namespace Tests\Feature;

use App\Models\Donation;
use App\Services\DiscordDonationLeaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishDonationLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_publish_without_payment_configuration(): void
    {
        config()->set('services.pakasir.slug', null);

        $this->artisan('donations:publish-leaderboard')->assertExitCode(1);
    }

    public function test_publishes_and_refreshes_a_single_board_with_paid_donors_only(): void
    {
        config()->set('services.pakasir.sandbox', true);
        config()->set('services.discord.bot_token', 'test-token');
        config()->set('services.discord.guild_id', '1542836975386624130');
        config()->set('services.discord.donation_channel_id', '1554268154501271552');
        Http::fake(['discord.com/*' => Http::response(['id' => '999999999999999999'])]);

        Donation::query()->create([
            'order_id' => 'don-paid', 'discord_interaction_id' => '111111111111111111',
            'discord_user_id' => '222222222222222222', 'discord_username' => 'donor',
            'guild_id' => '1542836975386624130', 'channel_id' => '1554268154501271552',
            'amount' => 50000, 'message' => 'Semangat belajar!', 'image_url' => 'https://example.com/image.gif',
            'is_sandbox' => true, 'status' => 'paid',
        ]);
        Donation::query()->create([
            'order_id' => 'don-pending', 'discord_interaction_id' => '333333333333333333',
            'discord_user_id' => '444444444444444444', 'discord_username' => 'other',
            'guild_id' => '1542836975386624130', 'channel_id' => '1554268154501271552',
            'amount' => 100000, 'is_sandbox' => true, 'status' => 'pending',
        ]);

        $leaderboard = app(DiscordDonationLeaderboard::class);
        $leaderboard->publish();
        $leaderboard->publish();

        $this->assertDatabaseCount('donation_leaderboards', 1);
        $payload = $leaderboard->payload();
        $this->assertStringContainsString('<@222222222222222222>', $payload['embeds'][0]['description']);
        $this->assertStringNotContainsString('<@444444444444444444>', $payload['embeds'][0]['description']);
        $this->assertSame('Rp50.000', $payload['embeds'][0]['fields'][0]['value']);
        $this->assertStringContainsString('Semangat belajar!', $payload['embeds'][0]['fields'][3]['value']);
        $this->assertSame('https://example.com/image.gif', $payload['embeds'][0]['image']['url']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bot test-token'));
        Http::assertSentCount(2);
    }
}

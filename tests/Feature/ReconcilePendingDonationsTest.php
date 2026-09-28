<?php

namespace Tests\Feature;

use App\Jobs\RefreshDonationLeaderboard;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconcilePendingDonationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pakasir.slug', 'test-project');
        config()->set('services.pakasir.api_key', 'test-api-key');
        $this->travelTo('2026-09-29 12:00:00');
    }

    public function test_confirmed_payment_becomes_paid_and_refreshes_the_leaderboard(): void
    {
        $donation = $this->pendingDonation();
        Queue::fake([RefreshDonationLeaderboard::class]);
        Http::preventStrayRequests();
        Http::fake(['https://app.pakasir.com/api/v2/transaction-status/test-project/txn-test' => Http::response($this->providerStatus())]);

        $this->artisan('donations:reconcile-pending')->assertExitCode(0);

        $this->assertSame('paid', $donation->fresh()->status);
        Queue::assertPushed(RefreshDonationLeaderboard::class, 1);
        Http::assertSentCount(1);
    }

    public function test_pending_payment_is_rechecked_later_without_changing_the_leaderboard(): void
    {
        $donation = $this->pendingDonation();
        Queue::fake([RefreshDonationLeaderboard::class]);
        Http::preventStrayRequests();
        Http::fake(['https://app.pakasir.com/api/v2/transaction-status/test-project/txn-test' => Http::response([...$this->providerStatus(), 'status' => 'pending'])]);

        $this->artisan('donations:reconcile-pending')->assertExitCode(0);

        $this->assertSame('pending', $donation->fresh()->status);
        $this->assertSame('2026-09-29 12:00:00', $donation->fresh()->updated_at->format('Y-m-d H:i:s'));
        Queue::assertNotPushed(RefreshDonationLeaderboard::class);
    }

    public function test_provider_mismatch_never_marks_a_donation_paid(): void
    {
        $donation = $this->pendingDonation();
        Queue::fake([RefreshDonationLeaderboard::class]);
        Http::preventStrayRequests();
        Http::fake(['https://app.pakasir.com/api/v2/transaction-status/test-project/txn-test' => Http::response([...$this->providerStatus(), 'amount' => 100000])]);

        $this->artisan('donations:reconcile-pending')->assertExitCode(0);

        $this->assertSame('pending', $donation->fresh()->status);
        Queue::assertNotPushed(RefreshDonationLeaderboard::class);
    }

    public function test_recent_pending_payment_is_not_polled_prematurely(): void
    {
        $donation = $this->pendingDonation();
        $donation->update(['updated_at' => now()]);
        Http::preventStrayRequests();
        Http::fake();

        $this->artisan('donations:reconcile-pending')->assertExitCode(0);

        Http::assertNothingSent();
    }

    private function pendingDonation(): Donation
    {
        $donation = Donation::query()->create([
            'order_id' => 'don-test',
            'discord_interaction_id' => '123456789012345678',
            'discord_user_id' => '111111111111111111',
            'discord_username' => 'donor',
            'guild_id' => '1542836975386624130',
            'channel_id' => '1554268154501271552',
            'amount' => 50000,
            'pakasir_txn_id' => 'txn-test',
            'is_sandbox' => false,
        ]);
        $donation->forceFill(['created_at' => now()->subMinutes(3), 'updated_at' => now()->subMinutes(2)])->save();

        return $donation;
    }

    /** @return array<string, mixed> */
    private function providerStatus(): array
    {
        return ['txn_id' => 'txn-test', 'order_id' => 'don-test', 'amount' => 50000, 'is_sandbox' => false, 'status' => 'completed'];
    }
}

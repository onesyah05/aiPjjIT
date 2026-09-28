<?php

namespace Tests\Feature;

use App\Jobs\RefreshDonationLeaderboard;
use App\Models\Donation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PakasirWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.pakasir.slug', 'test-project');
        config()->set('services.pakasir.api_key', 'test-api-key');
        config()->set('services.pakasir.webhook_secret', 'test-secret');
        Queue::fake();
    }

    public function test_rejects_a_webhook_without_correct_secret(): void
    {
        $this->postJson('/payments/pakasir/webhook', $this->webhook())->assertUnauthorized();
    }

    public function test_confirms_payment_against_provider_and_updates_leaderboard_once(): void
    {
        $donation = $this->donation();
        Http::fake(['app.pakasir.com/*' => Http::response($this->webhook())]);

        $this->withHeader('X-Secret', 'test-secret')->postJson('/payments/pakasir/webhook', $this->webhook())->assertOk();
        $this->withHeader('X-Secret', 'test-secret')->postJson('/payments/pakasir/webhook', $this->webhook())->assertOk();

        $this->assertEquals('paid', $donation->fresh()->status);
        $this->assertNotNull($donation->fresh()->paid_at);
        Queue::assertPushed(RefreshDonationLeaderboard::class, 1);
        Http::assertSentCount(1);
    }

    public function test_rejects_unconfirmed_provider_status(): void
    {
        $donation = $this->donation();
        Http::fake(['app.pakasir.com/*' => Http::response([...$this->webhook(), 'status' => 'pending'])]);

        $this->withHeader('X-Secret', 'test-secret')->postJson('/payments/pakasir/webhook', $this->webhook())->assertStatus(409);

        $this->assertEquals('pending', $donation->fresh()->status);
        Queue::assertNotPushed(RefreshDonationLeaderboard::class);
    }

    public function test_rejects_mismatched_amount_without_contacting_provider(): void
    {
        $this->donation();
        Http::fake();

        $this->withHeader('X-Secret', 'test-secret')->postJson('/payments/pakasir/webhook', [...$this->webhook(), 'amount' => 100000])->assertStatus(422);

        Http::assertNothingSent();
    }

    private function donation(): Donation
    {
        return Donation::query()->create([
            'order_id' => 'don-test',
            'discord_interaction_id' => '123456789012345678',
            'discord_user_id' => '111111111111111111',
            'discord_username' => 'donor',
            'guild_id' => '1542836975386624130',
            'channel_id' => '1554268154501271552',
            'amount' => 50000,
            'pakasir_txn_id' => 'txn-test',
            'is_sandbox' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function webhook(): array
    {
        return ['txn_id' => 'txn-test', 'order_id' => 'don-test', 'amount' => 50000, 'is_sandbox' => true, 'status' => 'completed'];
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\CreateDonationCheckout;
use App\Jobs\DeleteDonationInteractionResponse;
use App\Models\Donation;
use App\Services\PakasirClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DonationCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake([DeleteDonationInteractionResponse::class]);
    }

    public function test_checkout_creates_qris_and_sends_the_image_by_dm(): void
    {
        $donation = $this->donation();
        Http::fake([
            'app.pakasir.com/*' => Http::response($this->transaction()),
            'discord.com/api/v10/users/@me/channels' => Http::response(['id' => '333333333333333333']),
            'discord.com/api/v10/channels/*/messages' => Http::response(['id' => '444444444444444444']),
            'discord.com/api/v10/webhooks/*' => Http::response([], 200),
        ]);

        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->handle(app(PakasirClient::class));

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'pakasir_txn_id' => 'txn-test',
            'total_payment' => 50660,
            'dm_message_id' => '444444444444444444',
            'status' => 'pending',
        ]);
        $this->assertSame('000201QRIS-TEST', $donation->fresh()->qr_string);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://app.pakasir.com/api/v2/create-transaction/test-project/don-test'
            && $request['amount'] === 50000
            && $request['method'] === 'qris');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://discord.com/api/v10/users/@me/channels'
            && $request['recipient_id'] === '111111111111111111');
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://discord.com/api/v10/channels/333333333333333333/messages'
            && $request->isMultipart()
            && $request->hasFile('files[0]', filename: 'donasi-qris.png'));
        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/webhooks/222222222222222222/interaction-token/messages/@original')
            && str_contains($request['content'], 'QRIS SANDBOX')
            && str_contains($request['content'], 'Jangan transfer uang sungguhan'));
        Queue::assertPushed(DeleteDonationInteractionResponse::class, fn (DeleteDonationInteractionResponse $job): bool => $job->applicationId === '222222222222222222'
            && $job->interactionToken === 'interaction-token'
            && $job->queue === 'discord'
            && $job->delay->between(now()->addSeconds(59), now()->addSeconds(61)));
    }

    public function test_retry_does_not_create_another_transaction_or_dm(): void
    {
        $donation = $this->donation();
        $donation->update([
            'pakasir_txn_id' => 'txn-test',
            'qr_string' => '000201QRIS-TEST',
            'total_payment' => 50660,
            'qris_expires_at' => now()->addHour(),
            'dm_message_id' => '444444444444444444',
        ]);
        Http::fake(['discord.com/api/v10/webhooks/*' => Http::response([], 200)]);

        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->handle(app(PakasirClient::class));

        Http::assertSentCount(1);
    }

    public function test_checkout_rejects_inconsistent_pakasir_response(): void
    {
        $donation = $this->donation();
        Http::fake(['app.pakasir.com/*' => Http::response(array_replace($this->transaction(), ['amount' => 60000]))]);

        $this->expectException(\RuntimeException::class);
        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->handle(app(PakasirClient::class));
    }

    public function test_live_donation_rejects_sandbox_qris_with_a_private_explanation(): void
    {
        $donation = $this->donation();
        $donation->update(['is_sandbox' => false]);
        Http::fake([
            'app.pakasir.com/*' => Http::response($this->transaction()),
            'discord.com/api/v10/webhooks/*' => Http::response([], 200),
        ]);

        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->handle(app(PakasirClient::class));

        $this->assertDatabaseHas('donations', [
            'id' => $donation->id,
            'status' => 'failed',
            'pakasir_txn_id' => null,
            'dm_message_id' => null,
        ]);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/webhooks/222222222222222222/interaction-token/messages/@original')
            && str_contains($request['content'], 'mode proyek Pakasir tidak sesuai'));
        Queue::assertPushed(DeleteDonationInteractionResponse::class, 1);
    }

    public function test_checkout_accepts_documented_expired_at_field(): void
    {
        $donation = $this->donation();
        $transaction = $this->transaction();
        $transaction['expired_at'] = $transaction['expires_at'];
        unset($transaction['expires_at']);
        Http::fake([
            'app.pakasir.com/*' => Http::response($transaction),
            'discord.com/api/v10/users/@me/channels' => Http::response(['id' => '333333333333333333']),
            'discord.com/api/v10/channels/*/messages' => Http::response(['id' => '444444444444444444']),
            'discord.com/api/v10/webhooks/*' => Http::response([], 200),
        ]);

        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->handle(app(PakasirClient::class));

        $this->assertSame('444444444444444444', $donation->fresh()->dm_message_id);
    }

    public function test_failure_after_sandbox_dm_keeps_the_private_notice_marked_as_test(): void
    {
        $donation = $this->donation();
        $donation->update(['dm_message_id' => '444444444444444444']);
        Http::fake(['discord.com/api/v10/webhooks/*' => Http::response([], 200)]);

        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->failed(new \RuntimeException('Discord response edit failed.'));

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request['content'], 'QRIS SANDBOX')
            && str_contains($request['content'], 'bukan pembayaran nyata'));
        Queue::assertPushed(DeleteDonationInteractionResponse::class, 1);
    }

    private function donation(): Donation
    {
        config()->set('services.pakasir.slug', 'test-project');
        config()->set('services.pakasir.api_key', 'test-key');
        config()->set('services.discord.bot_token', 'test-bot-token');

        return Donation::query()->create([
            'order_id' => 'don-test', 'discord_interaction_id' => '123456789012345678',
            'discord_user_id' => '111111111111111111', 'discord_username' => 'donor',
            'guild_id' => '1542836975386624130', 'channel_id' => '1554268154501271552',
            'amount' => 50000, 'is_sandbox' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function transaction(): array
    {
        return [
            'txn_id' => 'txn-test',
            'project' => 'test-project',
            'order_id' => 'don-test',
            'amount' => 50000,
            'total_payment' => 50660,
            'payment_method' => 'qris',
            'qr_string' => '000201QRIS-TEST',
            'expires_at' => now()->addHour()->toISOString(),
            'is_sandbox' => true,
        ];
    }
}

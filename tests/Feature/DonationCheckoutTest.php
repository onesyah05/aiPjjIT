<?php

namespace Tests\Feature;

use App\Jobs\CreateDonationCheckout;
use App\Models\Donation;
use App\Services\PakasirClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DonationCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_uses_pakasir_v2_and_sends_private_payment_link(): void
    {
        config()->set('services.pakasir.slug', 'test-project');
        config()->set('services.pakasir.api_key', 'test-key');

        $donation = Donation::query()->create([
            'order_id' => 'don-test', 'discord_interaction_id' => '123456789012345678',
            'discord_user_id' => '111111111111111111', 'discord_username' => 'donor',
            'guild_id' => '1542836975386624130', 'channel_id' => '1554268154501271552',
            'amount' => 50000, 'is_sandbox' => true,
        ]);
        Http::fake([
            'app.pakasir.com/*' => Http::response(['txn_id' => 'txn-test', 'payment_link' => 'https://app.pakasir.com/pay-v2/txn-test']),
            'discord.com/*' => Http::response([], 200),
        ]);

        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->handle(app(PakasirClient::class));

        $this->assertDatabaseHas('donations', ['id' => $donation->id, 'pakasir_txn_id' => 'txn-test', 'status' => 'pending']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://app.pakasir.com/api/v2/create-transaction/test-project/don-test'
            && $request['amount'] === 50000
            && $request['method'] === 'payment_link');
        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && str_contains($request->url(), '/webhooks/222222222222222222/interaction-token/messages/@original')
            && $request['components'][0]['components'][0]['url'] === 'https://app.pakasir.com/pay-v2/txn-test');
    }

    public function test_checkout_rejects_untrusted_payment_link(): void
    {
        config()->set('services.pakasir.slug', 'test-project');
        config()->set('services.pakasir.api_key', 'test-key');

        $donation = Donation::query()->create([
            'order_id' => 'don-test', 'discord_interaction_id' => '123456789012345678',
            'discord_user_id' => '111111111111111111', 'discord_username' => 'donor',
            'guild_id' => '1542836975386624130', 'channel_id' => '1554268154501271552',
            'amount' => 50000, 'is_sandbox' => true,
        ]);
        Http::fake(['app.pakasir.com/*' => Http::response(['txn_id' => 'txn-test', 'payment_link' => 'https://evil.example/pay'])]);

        $this->expectException(\RuntimeException::class);
        (new CreateDonationCheckout($donation->id, '222222222222222222', 'interaction-token'))->handle(app(PakasirClient::class));

    }
}

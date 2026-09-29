<?php

namespace Tests\Feature;

use App\Jobs\CreateDonationCheckout;
use App\Jobs\DeleteDonationInteractionResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DiscordInteractionControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();

        $keyPair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($keyPair);
        config()->set('services.discord.public_key', bin2hex(sodium_crypto_sign_publickey($keyPair)));
        config()->set('services.discord.guild_id', '1542836975386624130');
        config()->set('services.discord.donation_channel_id', '1554268154501271552');
        config()->set('services.pakasir.slug', 'test-project');
        config()->set('services.pakasir.api_key', 'test-key');
        config()->set('services.pakasir.webhook_secret', 'test-secret');
        Queue::fake([DeleteDonationInteractionResponse::class]);
    }

    public function test_rejects_unsigned_interactions(): void
    {
        $this->postJson('/discord/interactions', ['type' => 1])->assertUnauthorized();
    }

    public function test_responds_to_signed_ping(): void
    {
        $this->signedInteraction(['type' => 1])->assertOk()->assertJson(['type' => 1]);
    }

    public function test_donate_button_opens_a_native_modal(): void
    {
        $this->signedInteraction($this->interaction(3, ['custom_id' => 'donation:open']))
            ->assertOk()->assertJsonPath('type', 9)
            ->assertJsonPath('data.components.0.type', 18)
            ->assertJsonPath('data.components.0.component.custom_id', 'amount')
            ->assertJsonPath('data.components.0.component.style', 1)
            ->assertJsonPath('data.components.1.component.style', 2)
            ->assertJsonPath('data.components.2.component.style', 1);
    }

    public function test_valid_submission_is_queued_without_marking_it_paid(): void
    {
        Queue::fake();

        $this->signedInteraction($this->interaction(5, [
            'custom_id' => 'donation:submit',
            'components' => [
                ['type' => 18, 'component' => ['custom_id' => 'amount', 'value' => '50000']],
                ['type' => 18, 'component' => ['custom_id' => 'message', 'value' => 'Semangat!']],
            ],
        ]))->assertOk()->assertJsonPath('type', 5)->assertJsonPath('data.flags', 64);

        $this->assertDatabaseHas('donations', ['amount' => 50000, 'status' => 'pending', 'discord_user_id' => '111111111111111111']);
        Queue::assertPushed(CreateDonationCheckout::class, 1);
    }

    public function test_invalid_amount_does_not_create_donation(): void
    {
        $this->signedInteraction($this->interaction(5, [
            'custom_id' => 'donation:submit',
            'components' => [['type' => 18, 'component' => ['custom_id' => 'amount', 'value' => '100']]],
        ]))->assertJsonPath('type', 4)->assertJsonPath('data.flags', 64);

        $this->assertDatabaseCount('donations', 0);
        Queue::assertPushed(DeleteDonationInteractionResponse::class, fn (DeleteDonationInteractionResponse $job): bool => $job->applicationId === '222222222222222222'
            && $job->interactionToken === 'test-token'
            && $job->queue === 'discord'
            && $job->delay->between(now()->addSeconds(59), now()->addSeconds(61)));
    }

    public function test_amount_above_qris_limit_does_not_create_donation(): void
    {
        $this->signedInteraction($this->interaction(5, [
            'custom_id' => 'donation:submit',
            'components' => [['type' => 18, 'component' => ['custom_id' => 'amount', 'value' => '10000001']]],
        ]))->assertJsonPath('type', 4)->assertJsonPath('data.flags', 64);

        $this->assertDatabaseCount('donations', 0);
    }

    /** @return array<string, mixed> */
    private function interaction(int $type, array $data): array
    {
        return [
            'id' => '123456789012345678',
            'type' => $type,
            'application_id' => '222222222222222222',
            'token' => 'test-token',
            'guild_id' => '1542836975386624130',
            'channel_id' => '1554268154501271552',
            'member' => ['user' => ['id' => '111111111111111111', 'username' => 'donor']],
            'data' => $data,
        ];
    }

    private function signedInteraction(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = bin2hex(sodium_crypto_sign_detached($timestamp.$body, $this->secretKey));

        return $this->call('POST', '/discord/interactions', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE_ED25519' => $signature,
            'HTTP_X_SIGNATURE_TIMESTAMP' => $timestamp,
        ], $body);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\DeleteDonationInteractionResponse;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class DeleteDonationInteractionResponseTest extends TestCase
{
    public function test_deletes_only_the_original_interaction_response(): void
    {
        Http::preventStrayRequests();
        Http::fake(['discord.com/api/v10/webhooks/*/messages/@original' => Http::response(status: 204)]);

        (new DeleteDonationInteractionResponse('222222222222222222', 'interaction-token'))->handle();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === 'https://discord.com/api/v10/webhooks/222222222222222222/interaction-token/messages/@original');
    }

    public function test_already_deleted_response_is_harmless(): void
    {
        Http::preventStrayRequests();
        Http::fake(['discord.com/api/v10/webhooks/*/messages/@original' => Http::response(status: 404)]);

        (new DeleteDonationInteractionResponse('222222222222222222', 'interaction-token'))->handle();

        Http::assertSentCount(1);
    }

    public function test_temporary_discord_failure_is_retried(): void
    {
        Http::preventStrayRequests();
        Http::fake(['discord.com/api/v10/webhooks/*/messages/@original' => Http::response(status: 500)]);

        $this->expectException(RuntimeException::class);

        (new DeleteDonationInteractionResponse('222222222222222222', 'interaction-token'))->handle();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AiCredential;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiCredentialTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_credential_is_encrypted_and_never_returned_as_raw_secret(): void
    {
        $user = User::factory()->create();
        $secret = 'gemini-secret-key-value-12345';
        Http::preventStrayRequests();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['models' => []])]);

        $response = $this->actingAs($user)->post(route('ai-credentials.store'), [
            'label' => 'Credential pribadi',
            'secret' => $secret,
            'community_enabled' => true,
            'daily_request_limit' => 25,
            'consent' => true,
        ]);

        $response->assertRedirect();
        $credential = AiCredential::query()->sole();
        $this->assertSame($secret, $credential->encrypted_secret);
        $this->assertNotSame($secret, DB::table('ai_credentials')->value('encrypted_secret'));
        $this->assertSame('••••2345', $credential->masked_preview);
    }

    public function test_member_cannot_disable_another_members_credential(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $credential = AiCredential::query()->create([
            'user_id' => $owner->id,
            'provider' => 'gemini',
            'credential_type' => 'api_key',
            'encrypted_secret' => 'gemini-secret-key-value-12345',
            'fingerprint' => hash('sha256', 'gemini-secret-key-value-12345'),
            'label' => 'Credential pribadi',
            'masked_preview' => '••••2345',
            'status' => 'active',
            'community_enabled' => true,
        ]);

        $this->actingAs($otherUser)
            ->patch(route('ai-credentials.update', $credential), ['status' => 'disabled'])
            ->assertForbidden();

        $this->assertSame('active', $credential->fresh()->status);
    }
}

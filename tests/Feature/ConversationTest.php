<?php

namespace Tests\Feature;

use App\Models\AiCredential;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authenticated_member_can_create_a_conversation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('conversations.store'), [
            'mode' => 'knowledge_only',
        ]);

        $conversation = Conversation::query()->sole();
        $response->assertRedirect(route('conversations.show', $conversation));
        $this->assertSame($user->id, $conversation->user_id);
        $this->assertSame('knowledge_only', $conversation->mode);
    }

    public function test_member_cannot_view_another_members_conversation(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::query()->create(['user_id' => $owner->id, 'mode' => 'general']);

        $this->actingAs($otherUser)
            ->get(route('conversations.show', $conversation))
            ->assertForbidden();
    }

    public function test_knowledge_only_mode_does_not_call_provider_without_relevant_sources(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create(['user_id' => $user->id, 'mode' => 'knowledge_only']);
        Http::preventStrayRequests();

        $response = $this->actingAs($user)->post(route('conversations.messages.store', $conversation), [
            'message' => 'Apa itu algoritma?',
        ]);
        $content = $response->streamedContent();

        $response->assertOk();
        $this->assertStringContainsString('{"content":"belum"}', $content);
        $this->assertStringContainsString('{"content":"ditemukan"}', $content);
        $this->assertDatabaseHas('messages', ['conversation_id' => $conversation->id, 'role' => 'assistant']);
    }

    public function test_general_chat_uses_encrypted_community_credential_and_persists_answer(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create(['user_id' => $user->id, 'mode' => 'general']);
        AiCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'credential_type' => 'api_key',
            'encrypted_secret' => 'secret-key-for-testing-123',
            'fingerprint' => hash('sha256', 'secret-key-for-testing-123'),
            'label' => 'Test',
            'masked_preview' => '••••g123',
            'status' => 'active',
            'community_enabled' => true,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Algoritma adalah langkah terstruktur.']]]]],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('conversations.messages.store', $conversation), [
            'message' => 'Apa itu algoritma?',
        ]);
        $response->streamedContent();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'Algoritma adalah langkah terstruktur.',
        ]);
    }
}

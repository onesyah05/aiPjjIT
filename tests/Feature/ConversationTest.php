<?php

namespace Tests\Feature;

use App\Models\AiCredential;
use App\Models\Conversation;
use App\Models\Knowledge;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeVersion;
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

    public function test_answer_includes_a_clickable_link_from_retrieved_knowledge(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create(['user_id' => $user->id, 'mode' => 'general']);
        $knowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Dokumentasi Laravel',
            'description' => 'Referensi framework Laravel.',
            'visibility' => 'community',
            'status' => 'approved',
        ]);
        $version = KnowledgeVersion::query()->create([
            'knowledge_id' => $knowledge->id,
            'version' => 1,
            'content' => 'Dokumentasi Laravel dapat dibaca melalui [panduan resmi](https://laravel.com/docs/13.x).',
            'source_type' => 'manual',
            'status' => 'approved',
            'processing_status' => 'ready',
        ]);
        $knowledge->update(['active_version_id' => $version->id]);
        KnowledgeChunk::query()->create([
            'knowledge_version_id' => $version->id,
            'chunk_index' => 0,
            'heading_path' => 'Referensi',
            'content' => $version->content,
            'token_count' => 8,
        ]);
        AiCredential::query()->create([
            'user_id' => $user->id,
            'provider' => 'gemini',
            'credential_type' => 'api_key',
            'encrypted_secret' => 'secret-key-for-link-test-123',
            'fingerprint' => hash('sha256', 'secret-key-for-link-test-123'),
            'label' => 'Test tautan',
            'masked_preview' => '••••t123',
            'status' => 'active',
            'community_enabled' => true,
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'Silakan pelajari dokumentasi resmi Laravel.']]]]],
            ]),
        ]);

        $response = $this->actingAs($user)->post(route('conversations.messages.store', $conversation), [
            'message' => 'Di mana dokumentasi Laravel?',
        ]);
        $streamedContent = $response->streamedContent();

        $response->assertOk();
        $this->assertStringContainsString('### Tautan terkait', $streamedContent);
        $this->assertStringContainsString('https:\/\/laravel.com\/docs\/13.x', $streamedContent);
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => "Silakan pelajari dokumentasi resmi Laravel.\n\n### Tautan terkait\n- [panduan resmi](https://laravel.com/docs/13.x)",
        ]);
        Http::assertSent(fn ($request): bool => str_contains(
            (string) data_get($request->data(), 'contents.0.parts.0.text'),
            'https://laravel.com/docs/13.x',
        ));
    }
}

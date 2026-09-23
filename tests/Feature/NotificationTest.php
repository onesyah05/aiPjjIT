<?php

namespace Tests\Feature;

use App\Models\AiCredential;
use App\Models\Knowledge;
use App\Models\User;
use App\Notifications\KnowledgeReviewUpdated;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_review_result_creates_an_in_app_notification_for_the_author(): void
    {
        $author = User::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $knowledge = Knowledge::query()->create([
            'user_id' => $author->id,
            'title' => 'Algoritma Dasar',
            'visibility' => 'community',
            'status' => 'pending_review',
        ]);
        $version = $knowledge->versions()->create([
            'version' => 1,
            'content' => '# Algoritma',
            'source_type' => 'manual',
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);

        $response = $this->actingAs($reviewer)->patch(route('admin.knowledge-reviews.update', $version), [
            'action' => 'reject',
            'note' => 'Sumber materi perlu diperjelas.',
        ]);

        $response->assertRedirect();
        $notification = $author->notifications()->sole();
        $this->assertSame('knowledge_review', $notification->data['kind']);
        $this->assertSame('rejected', $notification->data['status']);
        $this->assertStringContainsString('Sumber materi perlu diperjelas.', $notification->data['message']);
    }

    public function test_admin_disabling_a_credential_notifies_its_owner_without_exposing_the_secret(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create();
        $credential = AiCredential::query()->create([
            'user_id' => $owner->id,
            'provider' => 'gemini',
            'credential_type' => 'api_key',
            'encrypted_secret' => 'secret-value-that-must-not-leak',
            'fingerprint' => hash('sha256', 'credential'),
            'label' => 'Project belajar',
            'masked_preview' => '••••1234',
            'status' => 'active',
            'community_enabled' => true,
        ]);

        $response = $this->actingAs($admin)->patch(route('admin.credentials.update', $credential), [
            'status' => 'disabled',
        ]);

        $response->assertRedirect();
        $notification = $owner->notifications()->sole();
        $this->assertSame('credential_health', $notification->data['kind']);
        $this->assertSame('disabled', $notification->data['status']);
        $this->assertStringNotContainsString('secret-value-that-must-not-leak', json_encode($notification->data, JSON_THROW_ON_ERROR));
    }

    public function test_user_can_read_their_notification_and_is_redirected_to_its_destination(): void
    {
        $user = User::factory()->create();
        $knowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Basis Data',
            'visibility' => 'private',
            'status' => 'approved',
        ]);
        $user->notify(new KnowledgeReviewUpdated($knowledge->id, $knowledge->title, 'approved'));
        $notification = $user->notifications()->sole();

        $response = $this->actingAs($user)->post(route('notifications.read', $notification));

        $response->assertRedirect(route('knowledge.show', $knowledge));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_cannot_read_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $knowledge = Knowledge::query()->create([
            'user_id' => $owner->id,
            'title' => 'Jaringan Komputer',
            'visibility' => 'private',
            'status' => 'approved',
        ]);
        $owner->notify(new KnowledgeReviewUpdated($knowledge->id, $knowledge->title, 'approved'));
        $notification = $owner->notifications()->sole();

        $this->actingAs($otherUser)
            ->post(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }
}

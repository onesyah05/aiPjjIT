<?php

namespace Tests\Feature;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Knowledge;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KnowledgeModerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reviewer_can_approve_pending_community_knowledge(): void
    {
        $author = User::factory()->create();
        $reviewer = User::factory()->create(['role' => 'reviewer']);
        $knowledge = Knowledge::query()->create([
            'user_id' => $author->id,
            'title' => 'Struktur Data',
            'visibility' => 'community',
            'status' => 'pending_review',
        ]);
        $version = $knowledge->versions()->create([
            'version' => 1,
            'content' => '# Stack',
            'source_type' => 'manual',
            'status' => 'pending_review',
            'submitted_at' => now(),
        ]);
        Queue::fake([ProcessKnowledgeEmbedding::class]);

        $response = $this->actingAs($reviewer)->patch(route('admin.knowledge-reviews.update', $version), [
            'action' => 'approve',
        ]);

        $response->assertRedirect();
        $this->assertSame('approved', $version->fresh()->status);
        $this->assertSame('approved', $knowledge->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.approve', 'resource_id' => $version->id]);
        Queue::assertPushed(ProcessKnowledgeEmbedding::class);
    }

    public function test_student_cannot_review_community_knowledge(): void
    {
        $student = User::factory()->create();
        $knowledge = Knowledge::query()->create([
            'user_id' => $student->id,
            'title' => 'Struktur Data',
            'visibility' => 'community',
            'status' => 'pending_review',
        ]);
        $version = $knowledge->versions()->create([
            'version' => 1,
            'content' => '# Stack',
            'source_type' => 'manual',
            'status' => 'pending_review',
        ]);

        $this->actingAs($student)
            ->patch(route('admin.knowledge-reviews.update', $version), ['action' => 'approve'])
            ->assertForbidden();

        $this->assertSame('pending_review', $version->fresh()->status);
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Conversation;
use App\Models\Knowledge;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FeatureExpansionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_administrators_can_open_the_admin_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard')->has('stats'));

        $this->actingAs(User::factory()->create(['role' => 'student']))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_member_can_rate_an_assistant_message_in_their_conversation(): void
    {
        $user = User::factory()->create();
        $conversation = Conversation::query()->create(['user_id' => $user->id, 'mode' => 'general']);
        $message = $conversation->messages()->create(['role' => 'assistant', 'content' => 'Jawaban', 'status' => 'completed']);

        $this->actingAs($user)->post(route('messages.feedback.store', $message), [
            'rating' => 'helpful',
        ])->assertRedirect();

        $this->assertDatabaseHas('user_feedback', [
            'user_id' => $user->id,
            'message_id' => $message->id,
            'rating' => 'helpful',
        ]);
    }

    public function test_editing_private_knowledge_creates_a_new_active_version(): void
    {
        Queue::fake([ProcessKnowledgeEmbedding::class]);
        $user = User::factory()->create();
        $knowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Versi awal',
            'visibility' => 'private',
            'status' => 'approved',
        ]);
        $firstVersion = $knowledge->versions()->create([
            'version' => 1,
            'content' => 'Isi awal',
            'source_type' => 'manual',
            'status' => 'approved',
        ]);
        $knowledge->update(['active_version_id' => $firstVersion->id]);

        $this->actingAs($user)->patch(route('knowledge.update', $knowledge), [
            'title' => 'Versi baru',
            'context' => 'general',
            'visibility' => 'private',
            'content' => 'Isi yang diperbarui',
        ])->assertRedirect();

        $knowledge->refresh();
        $this->assertSame(2, $knowledge->versions()->count());
        $this->assertSame('Isi yang diperbarui', $knowledge->activeVersion->content);
        Queue::assertPushed(ProcessKnowledgeEmbedding::class);
    }

    public function test_leaderboard_orders_members_by_contribution_score(): void
    {
        $contributor = User::factory()->create(['name' => 'Kontributor utama']);
        Knowledge::query()->create([
            'user_id' => $contributor->id,
            'title' => 'Materi',
            'visibility' => 'community',
            'status' => 'approved',
        ]);
        User::factory()->create(['name' => 'Anggota baru']);

        $this->actingAs($contributor)
            ->get(route('leaderboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Leaderboard')
                ->where('leaders.0.name', 'Kontributor utama')
                ->where('leaders.0.score', 25));
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticatedNavigationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function studentMenuPages(): array
    {
        return [
            'dashboard' => ['dashboard', 'Dashboard'],
            'conversations' => ['conversations.index', 'Conversations/Index'],
            'knowledge' => ['knowledge.index', 'Knowledge/Index'],
            'contributions' => ['ai-credentials.index', 'Contributions/Index'],
            'profile' => ['profile.edit', 'Profile/Edit'],
            'privacy' => ['privacy', 'Privacy'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function knowledgeReviewerRoles(): array
    {
        return [
            'reviewer' => ['reviewer'],
            'administrator' => ['admin'],
        ];
    }

    #[DataProvider('studentMenuPages')]
    public function test_active_student_can_open_each_sidebar_page(string $routeName, string $component): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route($routeName))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component($component));
    }

    #[DataProvider('knowledgeReviewerRoles')]
    public function test_authorized_role_can_open_knowledge_review_page(string $role): void
    {
        $reviewer = User::factory()->create(['role' => $role]);

        $this->actingAs($reviewer)
            ->get(route('admin.knowledge-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/KnowledgeReviews'));
    }

    public function test_student_cannot_open_knowledge_review_page(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('admin.knowledge-reviews.index'))
            ->assertForbidden();
    }

    public function test_logout_menu_action_ends_the_session(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }
}

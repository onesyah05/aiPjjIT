<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Knowledge;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function unauthorizedRoles(): array
    {
        return [
            'student' => ['student'],
            'reviewer' => ['reviewer'],
        ];
    }

    public function test_admin_can_open_course_management_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Course::factory()->create(['code' => 'IF101']);

        $this->actingAs($admin)
            ->get(route('admin.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Courses')
                ->has('courses', 1)
                ->where('courses.0.code', 'IF101'));
    }

    public function test_admin_can_create_a_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.courses.store'), [
            'code' => 'IF401',
            'name' => 'Rekayasa Perangkat Lunak',
            'semester' => 4,
            'description' => 'Pengembangan perangkat lunak terstruktur.',
            'status' => 'active',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'code' => 'IF401',
            'name' => 'Rekayasa Perangkat Lunak',
            'semester' => 4,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_update_and_deactivate_a_course(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create(['code' => 'IF101', 'status' => 'active']);

        $response = $this->actingAs($admin)->patch(route('admin.courses.update', $course), [
            'code' => 'IF102',
            'name' => 'Algoritma Lanjut',
            'semester' => 2,
            'description' => 'Materi algoritma lanjutan.',
            'status' => 'inactive',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', [
            'id' => $course->id,
            'code' => 'IF102',
            'name' => 'Algoritma Lanjut',
            'semester' => 2,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_delete_a_course_without_deleting_linked_knowledge(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $course = Course::factory()->create();
        $knowledge = Knowledge::query()->create([
            'user_id' => $admin->id,
            'course_id' => $course->id,
            'title' => 'Materi lama',
            'visibility' => 'private',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.courses.destroy', $course));

        $response->assertRedirect(route('admin.courses.index'));
        $this->assertModelMissing($course);
        $this->assertModelExists($knowledge);
        $this->assertNull($knowledge->fresh()->course_id);
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_admin_cannot_open_course_management(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)
            ->get(route('admin.courses.index'))
            ->assertForbidden();
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_admin_cannot_update_a_course(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $course = Course::factory()->create();

        $this->actingAs($user)
            ->patch(route('admin.courses.update', $course), [
                'code' => 'IF999',
                'name' => 'Tidak boleh berubah',
                'status' => 'inactive',
            ])
            ->assertForbidden();

        $this->assertSame($course->code, $course->fresh()->code);
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_admin_cannot_delete_a_course(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $course = Course::factory()->create();

        $this->actingAs($user)
            ->delete(route('admin.courses.destroy', $course))
            ->assertForbidden();

        $this->assertModelExists($course);
    }
}

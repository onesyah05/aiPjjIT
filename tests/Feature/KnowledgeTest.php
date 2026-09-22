<?php

namespace Tests\Feature;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Course;
use App\Models\Knowledge;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class KnowledgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function memberRoles(): array
    {
        return [
            'student' => ['student'],
            'reviewer' => ['reviewer'],
            'administrator' => ['admin'],
        ];
    }

    #[DataProvider('memberRoles')]
    public function test_each_member_role_can_create_private_general_knowledge(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        Queue::fake([ProcessKnowledgeEmbedding::class]);

        $response = $this->actingAs($user)->post(route('knowledge.store'), [
            'title' => 'Binary Search',
            'description' => 'Catatan pencarian biner',
            'context' => 'general',
            'visibility' => 'private',
            'content' => '# Binary Search\n\nMembagi ruang pencarian menjadi dua.',
        ]);

        $knowledge = Knowledge::query()->with('activeVersion')->sole();
        $response->assertRedirect(route('knowledge.index'));
        $this->assertNull($knowledge->course_id);
        $this->assertSame('approved', $knowledge->status);
        $this->assertSame('approved', $knowledge->activeVersion->status);
        Queue::assertPushed(ProcessKnowledgeEmbedding::class, fn ($job): bool => $job->knowledgeVersion->is($knowledge->activeVersion));
    }

    public function test_member_can_create_course_knowledge_from_text(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        Queue::fake([ProcessKnowledgeEmbedding::class]);

        $response = $this->actingAs($user)->post(route('knowledge.store'), [
            'title' => 'Normalisasi Basis Data',
            'context' => 'course',
            'course_id' => $course->id,
            'visibility' => 'course',
            'content' => 'Normalisasi mengurangi redundansi data.',
        ]);

        $knowledge = Knowledge::query()->with('activeVersion')->sole();
        $response->assertRedirect(route('knowledge.index'));
        $this->assertSame($course->id, $knowledge->course_id);
        $this->assertSame('course', $knowledge->visibility);
        $this->assertSame('pending_review', $knowledge->status);
        $this->assertSame('manual', $knowledge->activeVersion->source_type);
        Queue::assertPushed(ProcessKnowledgeEmbedding::class, fn ($job): bool => $job->knowledgeVersion->is($knowledge->activeVersion));
    }

    public function test_member_can_create_knowledge_from_text_when_file_field_is_null(): void
    {
        $user = User::factory()->create();
        Queue::fake([ProcessKnowledgeEmbedding::class]);

        $response = $this->actingAs($user)->post(route('knowledge.store'), [
            'title' => 'Struktur Data',
            'context' => 'general',
            'visibility' => 'private',
            'content' => '# Struktur Data\n\nMateri tentang organisasi data.',
            'file' => null,
        ]);

        $knowledge = Knowledge::query()->with('activeVersion')->sole();
        $response->assertRedirect(route('knowledge.index'));
        $this->assertSame('manual', $knowledge->activeVersion->source_type);
        $this->assertSame('# Struktur Data\n\nMateri tentang organisasi data.', $knowledge->activeVersion->content);
        Queue::assertPushed(ProcessKnowledgeEmbedding::class, fn ($job): bool => $job->knowledgeVersion->is($knowledge->activeVersion));
    }

    public function test_member_can_create_general_knowledge_from_markdown_file(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('algoritma.md', "# Algoritma\n\nLangkah terstruktur.");
        Queue::fake([ProcessKnowledgeEmbedding::class]);

        $response = $this->actingAs($user)->post(route('knowledge.store'), [
            'title' => 'Pengantar Algoritma',
            'context' => 'general',
            'visibility' => 'community',
            'file' => $file,
        ]);

        $knowledge = Knowledge::query()->with('activeVersion')->sole();
        $response->assertRedirect(route('knowledge.index'));
        $this->assertNull($knowledge->course_id);
        $this->assertSame('pending_review', $knowledge->status);
        $this->assertSame('md_upload', $knowledge->activeVersion->source_type);
        $this->assertSame('algoritma.md', $knowledge->activeVersion->original_filename);
        $this->assertSame("# Algoritma\n\nLangkah terstruktur.", $knowledge->activeVersion->content);
        Queue::assertPushed(ProcessKnowledgeEmbedding::class, fn ($job): bool => $job->knowledgeVersion->is($knowledge->activeVersion));
    }

    public function test_course_knowledge_requires_an_active_course(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('knowledge.store'), [
            'title' => 'Materi kuliah',
            'context' => 'course',
            'visibility' => 'course',
            'content' => 'Isi materi.',
        ]);

        $response->assertSessionHasErrors([
            'course_id' => 'Pilih mata kuliah untuk knowledge mata kuliah.',
        ]);
        $this->assertDatabaseEmpty('knowledges');
    }

    public function test_upload_rejects_a_file_that_is_not_markdown(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('catatan.txt', 'Isi catatan.');

        $response = $this->actingAs($user)->post(route('knowledge.store'), [
            'title' => 'Catatan teks',
            'context' => 'general',
            'visibility' => 'private',
            'file' => $file,
        ]);

        $response->assertSessionHasErrors([
            'file' => 'Berkas harus menggunakan ekstensi .md.',
        ]);
        $this->assertDatabaseEmpty('knowledges');
    }

    public function test_general_knowledge_cannot_use_course_visibility(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('knowledge.store'), [
            'title' => 'Materi umum',
            'context' => 'general',
            'visibility' => 'course',
            'content' => 'Isi materi.',
        ]);

        $response->assertSessionHasErrors('visibility');
        $this->assertDatabaseEmpty('knowledges');
    }

    public function test_general_and_course_filters_return_the_matching_knowledge(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $generalKnowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Materi umum',
            'visibility' => 'private',
            'status' => 'approved',
        ]);
        Knowledge::query()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'title' => 'Materi mata kuliah',
            'visibility' => 'private',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($user)->get(route('knowledge.index', ['context' => 'general']));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Knowledge/Index')
            ->where('filters.context', 'general')
            ->has('knowledge.data', 1)
            ->where('knowledge.data.0.id', $generalKnowledge->id)
            ->where('counts.general', 1)
            ->where('counts.course', 1));
    }

    public function test_private_knowledge_cannot_be_viewed_by_another_member(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $knowledge = Knowledge::query()->create([
            'user_id' => $owner->id,
            'title' => 'Catatan privat',
            'visibility' => 'private',
            'status' => 'approved',
        ]);

        $this->actingAs($otherUser)->get(route('knowledge.show', $knowledge))->assertForbidden();
    }
}

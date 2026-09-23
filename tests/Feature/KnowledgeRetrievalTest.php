<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Knowledge;
use App\Models\KnowledgeVersion;
use App\Models\User;
use App\Services\Knowledge\RetrievalService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KnowledgeRetrievalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_keyword_retrieval_only_uses_the_active_ready_version(): void
    {
        config()->set('services.qdrant.enabled', false);
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'mode' => 'general',
            'status' => 'active',
        ]);
        $knowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Algoritma',
            'visibility' => 'private',
            'status' => 'approved',
        ]);
        $oldVersion = $this->createVersion($knowledge, 1, 'approved', 'ready', 'algoritma versi lama');
        $activeVersion = $this->createVersion($knowledge, 2, 'approved', 'ready', 'algoritma versi terbaru');
        $knowledge->update(['active_version_id' => $activeVersion->id]);

        $results = app(RetrievalService::class)->retrieve($user, $conversation, 'algoritma');

        $this->assertCount(1, $results);
        $this->assertSame($activeVersion->chunks()->sole()->id, $results->first()['chunk']->id);
        $this->assertNotSame($oldVersion->chunks()->sole()->id, $results->first()['chunk']->id);
    }

    public function test_keyword_retrieval_excludes_an_active_version_until_processing_is_ready(): void
    {
        config()->set('services.qdrant.enabled', false);
        $user = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'mode' => 'knowledge_only',
            'status' => 'active',
        ]);
        $knowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Basis Data',
            'visibility' => 'private',
            'status' => 'approved',
        ]);
        $version = $this->createVersion($knowledge, 1, 'approved', 'processing', 'normalisasi basis data');
        $knowledge->update(['active_version_id' => $version->id]);

        $results = app(RetrievalService::class)->retrieve($user, $conversation, 'normalisasi');

        $this->assertCount(0, $results);
    }

    public function test_keyword_retrieval_does_not_expose_another_users_private_knowledge(): void
    {
        config()->set('services.qdrant.enabled', false);
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $conversation = Conversation::query()->create([
            'user_id' => $user->id,
            'mode' => 'general',
            'status' => 'active',
        ]);
        $knowledge = Knowledge::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'Catatan pribadi',
            'visibility' => 'private',
            'status' => 'approved',
        ]);
        $version = $this->createVersion($knowledge, 1, 'approved', 'ready', 'informasi privat rahasia');
        $knowledge->update(['active_version_id' => $version->id]);

        $results = app(RetrievalService::class)->retrieve($user, $conversation, 'rahasia');

        $this->assertCount(0, $results);
    }

    private function createVersion(
        Knowledge $knowledge,
        int $versionNumber,
        string $status,
        string $processingStatus,
        string $content,
    ): KnowledgeVersion {
        $version = $knowledge->versions()->create([
            'version' => $versionNumber,
            'content' => $content,
            'source_type' => 'manual',
            'status' => $status,
            'processing_status' => $processingStatus,
        ]);
        $version->chunks()->create([
            'chunk_index' => 0,
            'content' => $content,
            'token_count' => 3,
        ]);

        return $version;
    }
}

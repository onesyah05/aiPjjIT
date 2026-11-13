<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Knowledge;
use App\Models\KnowledgeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReindexKnowledgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_fresh_reindex_queues_embedding_without_chunk_reuse_and_keeps_knowledge_searchable(): void
    {
        config(['services.qdrant.enabled' => true]);
        Queue::fake();
        $knowledge = Knowledge::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Algoritma',
            'visibility' => 'community',
            'status' => 'approved',
        ]);
        $version = KnowledgeVersion::query()->create([
            'knowledge_id' => $knowledge->id,
            'version' => 1,
            'content' => 'Algoritma adalah langkah terstruktur.',
            'source_type' => 'manual',
            'status' => 'approved',
            'processing_status' => 'ready',
        ]);
        $knowledge->update(['active_version_id' => $version->id]);

        $this->artisan('knowledge:reindex', ['--fresh' => true])->assertSuccessful();

        Queue::assertPushed(
            ProcessKnowledgeEmbedding::class,
            fn (ProcessKnowledgeEmbedding $job): bool => $job->knowledgeVersion->is($version) && $job->reuseChunks === false,
        );
        $this->assertSame('ready', $version->fresh()->processing_status);
    }
}

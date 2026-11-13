<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Knowledge;
use App\Models\KnowledgeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProcessKnowledgeEmbeddingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reused_chunk_takes_its_new_position_without_colliding_with_new_chunks(): void
    {
        config(['services.qdrant.enabled' => false]);
        $user = User::factory()->create();
        $knowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Diskusi',
            'visibility' => 'community',
            'status' => 'approved',
        ]);
        $paragraphs = fn (string $prefix): string => collect(range(1, 4))
            ->map(fn (int $number): string => "{$prefix} paragraf {$number}")
            ->join("\n\n");

        $oldVersion = $this->createVersion($knowledge, 1, $paragraphs('lama')."\n\n".$paragraphs('tetap'));
        app()->call([new ProcessKnowledgeEmbedding($oldVersion), 'handle']);
        $oldVersion->update(['is_embedded' => true]);
        $reusedChunkId = $oldVersion->chunks()->where('chunk_index', 1)->value('id');

        $newVersion = $this->createVersion($knowledge, 2, $paragraphs('tetap')."\n\n".$paragraphs('baru'));
        app()->call([new ProcessKnowledgeEmbedding($newVersion), 'handle']);

        $chunks = $newVersion->chunks()->orderBy('chunk_index')->get();
        $this->assertSame([0, 1], $chunks->pluck('chunk_index')->all());
        $this->assertSame($reusedChunkId, $chunks->first()->id);
        $this->assertStringContainsString('baru paragraf 1', $chunks->last()->content);
        $this->assertSame('ready', $newVersion->fresh()->processing_status);
    }

    private function createVersion(Knowledge $knowledge, int $number, string $content): KnowledgeVersion
    {
        return KnowledgeVersion::query()->create([
            'knowledge_id' => $knowledge->id,
            'version' => $number,
            'content' => $content,
            'source_type' => 'manual',
            'status' => 'approved',
            'processing_status' => 'pending',
        ]);
    }
}

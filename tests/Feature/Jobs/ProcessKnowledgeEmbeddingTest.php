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

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.qdrant.enabled' => false]);
    }

    public function test_sliding_message_window_reuses_every_chunk_except_the_edges(): void
    {
        $knowledge = $this->createKnowledge();
        $oldVersion = $this->embedVersion($knowledge, 1, $this->messages(1, 60));
        $oldChunkIds = $oldVersion->chunks()->pluck('id');

        // The oldest message drops out and a new one arrives, like a Discord channel window.
        $newVersion = $this->embedVersion($knowledge, 2, $this->messages(2, 61));
        $newChunks = $newVersion->chunks()->orderBy('chunk_index')->get();

        $this->assertSame(range(0, $newChunks->count() - 1), $newChunks->pluck('chunk_index')->all());
        $this->assertGreaterThanOrEqual(
            $oldChunkIds->count() - 2,
            $newChunks->pluck('id')->intersect($oldChunkIds)->count(),
        );
        $this->assertSame('ready', $newVersion->fresh()->processing_status);
    }

    public function test_fresh_embedding_creates_new_chunks_instead_of_reusing_old_ones(): void
    {
        $knowledge = $this->createKnowledge();
        $oldVersion = $this->embedVersion($knowledge, 1, $this->messages(1, 20));
        $oldChunkIds = $oldVersion->chunks()->pluck('id');

        $newVersion = $this->embedVersion($knowledge, 2, $this->messages(1, 20), reuseChunks: false);

        $this->assertNotEmpty($newVersion->chunks()->pluck('id'));
        $this->assertEmpty($newVersion->chunks()->pluck('id')->intersect($oldChunkIds));
    }

    private function createKnowledge(): Knowledge
    {
        return Knowledge::query()->create([
            'user_id' => User::factory()->create()->id,
            'title' => 'Diskusi',
            'visibility' => 'community',
            'status' => 'approved',
        ]);
    }

    private function messages(int $from, int $to): string
    {
        return collect(range($from, $to))
            ->map(fn (int $number): string => "**mahasiswa{$number}**: pesan nomor {$number}")
            ->join("\n\n");
    }

    private function embedVersion(Knowledge $knowledge, int $number, string $content, bool $reuseChunks = true): KnowledgeVersion
    {
        $version = KnowledgeVersion::query()->create([
            'knowledge_id' => $knowledge->id,
            'version' => $number,
            'content' => $content,
            'source_type' => 'discord',
            'status' => 'approved',
            'processing_status' => 'pending',
        ]);

        app()->call([new ProcessKnowledgeEmbedding($version, $reuseChunks), 'handle']);
        // Qdrant is off in tests; mark it embedded so later versions may reuse its chunks.
        $version->update(['is_embedded' => true]);

        return $version;
    }
}

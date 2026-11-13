<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Knowledge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge:reindex {--fresh : Re-embed every chunk; required after changing the embedding provider or Qdrant collection}')]
#[Description('Queue all approved active knowledge versions for Qdrant re-indexing')]
class ReindexKnowledge extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('services.qdrant.enabled')) {
            $this->components->error('Qdrant belum aktif. Set QDRANT_ENABLED=true terlebih dahulu.');

            return self::FAILURE;
        }

        $queued = 0;
        $reuseChunks = ! $this->option('fresh');
        Knowledge::query()
            ->where('status', 'approved')
            ->whereNotNull('active_version_id')
            ->with('activeVersion')
            ->chunkById(100, function ($knowledges) use (&$queued, $reuseChunks): void {
                foreach ($knowledges as $knowledge) {
                    if ($knowledge->activeVersion?->status !== 'approved') {
                        continue;
                    }

                    // The version keeps its current status while queued so it stays
                    // searchable; the job marks it processing when it starts.
                    ProcessKnowledgeEmbedding::dispatch($knowledge->activeVersion, $reuseChunks);
                    $queued++;
                }
            });

        $this->components->info("{$queued} versi knowledge masuk antrean re-index.");

        return self::SUCCESS;
    }
}

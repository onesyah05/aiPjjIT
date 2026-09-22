<?php

namespace App\Console\Commands;

use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Knowledge;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('knowledge:reindex')]
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
        Knowledge::query()
            ->where('status', 'approved')
            ->whereNotNull('active_version_id')
            ->with('activeVersion')
            ->chunkById(100, function ($knowledges) use (&$queued): void {
                foreach ($knowledges as $knowledge) {
                    if ($knowledge->activeVersion?->status !== 'approved') {
                        continue;
                    }

                    $knowledge->activeVersion->update([
                        'processing_status' => 'pending',
                        'processing_error' => null,
                    ]);
                    ProcessKnowledgeEmbedding::dispatch($knowledge->activeVersion);
                    $queued++;
                }
            });

        $this->components->info("{$queued} versi knowledge masuk antrean re-index.");

        return self::SUCCESS;
    }
}

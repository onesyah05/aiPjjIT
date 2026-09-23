<?php

namespace App\Jobs;

use App\Services\Vector\QdrantService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteKnowledgeVectors implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 90];

    public function __construct(public int $knowledgeId) {}

    /**
     * Execute the job.
     */
    public function handle(QdrantService $qdrant): void
    {
        $qdrant->deleteKnowledge($this->knowledgeId);
    }
}

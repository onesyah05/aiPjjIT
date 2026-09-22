<?php

namespace App\Jobs;

use App\Models\AiRequestLog;
use App\Models\KnowledgeVersion;
use App\Services\AI\EmbeddingService;
use App\Services\CredentialPoolService;
use App\Services\Vector\QdrantService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessKnowledgeEmbedding implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public KnowledgeVersion $knowledgeVersion
    ) {}

    /**
     * Execute the job.
     */
    public function handle(EmbeddingService $embedding, QdrantService $qdrant, CredentialPoolService $credentialPool): void
    {
        $this->knowledgeVersion->update([
            'processing_status' => 'processing',
            'processing_error' => null,
            'is_embedded' => false,
        ]);

        $content = str_replace("\r\n", "\n", $this->knowledgeVersion->content);
        $paragraphs = preg_split('/\n{2,}/', $content) ?: [];
        $chunks = collect($paragraphs)
            ->map(fn (string $paragraph): string => trim($paragraph))
            ->filter()
            ->chunk(4)
            ->map(fn ($group): string => $group->join("\n\n"))
            ->values();

        $this->knowledgeVersion->chunks()->delete();

        $knowledgeChunks = $chunks->map(function (string $chunk, int $index) {
            return $this->knowledgeVersion->chunks()->create([
                'chunk_index' => $index,
                'heading_path' => $this->headingFor($chunk),
                'content' => $chunk,
                'token_count' => str_word_count(strip_tags($chunk)),
                'vector_external_id' => Str::uuid(),
            ]);
        });

        if (! $qdrant->enabled()) {
            $this->knowledgeVersion->update([
                'processing_status' => 'ready',
                'processing_error' => null,
            ]);

            return;
        }

        $credential = $credentialPool->getAvailableCredentials(1)->first();

        if ($credential === null) {
            throw new \RuntimeException('Tidak ada credential untuk membuat embedding.');
        }

        $startedAt = hrtime(true);
        $log = AiRequestLog::query()->create([
            'user_id' => $this->knowledgeVersion->knowledge->user_id,
            'credential_id' => $credential->id,
            'operation' => 'embedding',
            'provider' => $credential->provider,
            'model' => config('services.gemini.embedding_model'),
            'status' => 'processing',
            'input_tokens' => $knowledgeChunks->sum('token_count'),
        ]);

        try {
            foreach ($knowledgeChunks as $chunk) {
                $credentialPool->recordAttempt($credential);
                $vector = $embedding->embed($credential->encrypted_secret, $chunk->content);
                $chunk->loadMissing('version.knowledge');
                $qdrant->upsert($chunk, $vector);
                $credentialPool->recordSuccess($credential);
            }
        } catch (Throwable $exception) {
            $credentialPool->recordFailure($credential, 'embedding_or_vector_error');
            $log->update([
                'status' => 'failed',
                'error_code' => 'embedding_or_vector_error',
                'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ]);

            throw $exception;
        }

        $log->update([
            'status' => 'completed',
            'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
        ]);

        $this->knowledgeVersion->update([
            'is_embedded' => true,
            'processing_status' => 'ready',
            'processing_error' => null,
            'embedded_at' => now(),
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->knowledgeVersion->id;
    }

    public function failed(?Throwable $exception): void
    {
        $this->knowledgeVersion->update([
            'is_embedded' => false,
            'processing_status' => 'failed',
            'processing_error' => Str::limit($exception?->getMessage() ?? 'Pemrosesan embedding gagal.', 1000),
        ]);
    }

    private function headingFor(string $content): ?string
    {
        if (preg_match('/^#{1,6}\s+(.+)$/m', $content, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }
}

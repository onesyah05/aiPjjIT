<?php

namespace App\Jobs;

use App\Models\AiRequestLog;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeVersion;
use App\Services\AI\EmbeddingService;
use App\Services\CredentialPoolService;
use App\Services\Vector\QdrantService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ProcessKnowledgeEmbedding implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [15, 60, 180];

    public int $timeout = 300;

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

        // Chunks whose content is unchanged from an already-embedded version of
        // this knowledge are reused as-is: their Qdrant point stays valid and no
        // embedding quota is spent on them.
        $reusable = KnowledgeChunk::query()
            ->where('content_hash', '!=', '')
            ->whereNotNull('content_hash')
            ->whereHas('version', function ($query): void {
                $query->where('knowledge_id', $this->knowledgeVersion->knowledge_id)
                    ->where('is_embedded', true)
                    ->where('id', '!=', $this->knowledgeVersion->id);
            })
            ->orderByDesc('id')
            ->get()
            ->keyBy('content_hash');

        $reusedPointIds = [];

        $this->knowledgeVersion->chunks()->delete();

        $embedTitle = (string) $this->knowledgeVersion->knowledge->title;

        $knowledgeChunks = $chunks->map(function (string $chunk, int $index) use ($reusable, $embedTitle, &$reusedPointIds) {
            // Store the knowledge title with the chunk so keyword search, merge
            // scoring, and the AI prompt all keep the source context.
            $chunk = $embedTitle.'
'.$chunk;
            $contentHash = md5($chunk);
            $previous = $reusable->get($contentHash);

            if ($previous !== null) {
                $previous->update(['knowledge_version_id' => $this->knowledgeVersion->id]);
                $reusedPointIds[] = $previous->vector_external_id;
                // Identical short chunks can occur many times; each reuse may
                // claim the previous row only once.
                $reusable->forget($contentHash);

                return $previous;
            }

            return $this->knowledgeVersion->chunks()->create([
                'chunk_index' => $index,
                'heading_path' => $this->headingFor($chunk),
                'content' => $chunk,
                'content_hash' => $contentHash,
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

        $chunksToEmbed = $knowledgeChunks->reject(
            fn (KnowledgeChunk $chunk): bool => in_array($chunk->vector_external_id, $reusedPointIds, true),
        )->values();

        if ($reusedPointIds !== []) {
            $qdrant->updatePayloadVersion(
                $reusedPointIds,
                $this->knowledgeVersion->knowledge_id,
                $this->knowledgeVersion->id,
            );
        }

        if ($chunksToEmbed->isEmpty()) {
            $this->finalizeEmbedding($qdrant);

            return;
        }

        $provider = $credentialPool->embeddingProvider();
        $batchSize = $provider === 'voyage'
            ? max(1, (int) config('services.voyage.batch_size', 32))
            : 1;

        // Start with one credential of the configured embedding provider — a
        // collection can only hold vectors from a single provider.
        $credential = $credentialPool->getAvailableCredentials(1, $provider)->first();

        if ($credential === null) {
            throw new \RuntimeException("Tidak ada credential {$provider} untuk membuat embedding.");
        }

        $startedAt = hrtime(true);
        $log = AiRequestLog::query()->create([
            'user_id' => $this->knowledgeVersion->knowledge->user_id,
            'credential_id' => $credential->id,
            'operation' => 'embedding',
            'provider' => $provider,
            'model' => $provider === 'voyage'
                ? (string) config('services.voyage.model')
                : (string) config('services.gemini.embedding_model'),
            'status' => 'processing',
            'input_tokens' => $knowledgeChunks->sum('token_count'),
        ]);

        try {
            // Clear orphans from previous failed attempts of this same version.
            // Old-version vectors are only removed after every chunk succeeds,
            // so a failed run can never wipe the searchable knowledge.
            $qdrant->deleteVersion($this->knowledgeVersion->knowledge_id, $this->knowledgeVersion->id);

            foreach ($chunksToEmbed->values()->chunk($batchSize)->values() as $batchIndex => $batch) {
                if ($batchIndex > 0 && $provider === 'voyage') {
                    // Stay inside the free tier's requests-per-minute limit.
                    sleep(max(1, (int) config('services.voyage.rpm_delay', 21)));
                }

                $retryCount = 0;
                $embedded = false;

                while (! $embedded && $retryCount < 5) {
                    if ($credential === null) {
                        $credential = $credentialPool->getAvailableCredentials(1, $provider)->first();
                        if ($credential === null) {
                            // If absolutely no credentials, wait a bit
                            sleep(10);
                            $retryCount++;

                            continue;
                        }
                    }

                    try {
                        $credentialPool->recordAttempt($credential);
                        $vectors = $embedding->embedBatch(
                            $provider,
                            $credential->encrypted_secret,
                            $batch->map(fn (KnowledgeChunk $chunk): string => $chunk->content)->all(),
                        );

                        foreach ($batch->values() as $chunkIndex => $chunk) {
                            $chunk->loadMissing('version.knowledge');
                            $qdrant->upsert($chunk, $vectors[$chunkIndex]);
                        }

                        $credentialPool->recordSuccess($credential);
                        $embedded = true;
                    } catch (Throwable $exception) {
                        $credentialPool->recordFailure($credential, 'embedding_or_vector_error');

                        $rateLimited = false;

                        if ($exception instanceof RequestException) {
                            $status = $exception->response->status();

                            if (in_array($status, [401, 403], true)) {
                                $credentialPool->markInvalid(
                                    $credential,
                                    'embedding_auth_failed',
                                    'Credential ditolak saat memproses embedding knowledge. Periksa key dan izin project Anda.',
                                );
                            } elseif (in_array($status, [429, 503], true)) {
                                // Put this credential on cooldown for 30 seconds
                                Cache::put("credential_cooldown_{$credential->id}", true, now()->addSeconds(30));
                                $rateLimited = true;
                            }
                        }

                        // Discard current credential, try next one
                        $credential = null;
                        $retryCount++;

                        if ($rateLimited && $provider === 'voyage') {
                            // Only a handful of requests per minute are allowed.
                            sleep(max(1, (int) config('services.voyage.rpm_delay', 21)));
                        } else {
                            sleep(2); // Short sleep before trying next credential
                        }
                    }
                }

                if (! $embedded) {
                    throw new \RuntimeException('Gagal memproses embedding setelah 5 kali percobaan (Mungkin limit API).');
                }
            }
        } catch (Throwable $exception) {
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

        $this->finalizeEmbedding($qdrant);
    }

    /**
     * Mark the version ready once all of its chunks are searchable and retire
     * the superseded version's points.
     */
    private function finalizeEmbedding(QdrantService $qdrant): void
    {
        // Every chunk of this version is live — now retire the previous version's points.
        $qdrant->deleteKnowledge($this->knowledgeVersion->knowledge_id, $this->knowledgeVersion->id);

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

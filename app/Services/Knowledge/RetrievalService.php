<?php

namespace App\Services\Knowledge;

use App\Models\Conversation;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\AI\EmbeddingService;
use App\Services\CredentialPoolService;
use App\Services\Vector\QdrantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class RetrievalService
{
    public function __construct(
        private EmbeddingService $embedding,
        private QdrantService $qdrant,
        private CredentialPoolService $credentialPool,
    ) {}

    /** @return Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}> */
    public function retrieve(User $user, Conversation $conversation, string $question, int $limit = 5): Collection
    {
        $vectorResults = $this->retrieveFromVectorStore($user, $conversation, $question, $limit);

        if ($vectorResults->isNotEmpty()) {
            return $vectorResults;
        }

        return $this->retrieveByKeywords($user, $conversation, $question, $limit);
    }

    /** @return Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}> */
    private function retrieveFromVectorStore(User $user, Conversation $conversation, string $question, int $limit): Collection
    {
        if (! $this->qdrant->enabled()) {
            return collect();
        }

        $credential = $this->credentialPool->getAvailableCredentials(1)->first();

        if ($credential === null) {
            return collect();
        }

        try {
            $this->credentialPool->recordAttempt($credential);
            $vector = $this->embedding->embed($credential->encrypted_secret, $question, 'RETRIEVAL_QUERY');
            $points = $this->qdrant->query($vector, $user, $conversation->course_id, $limit);
            $this->credentialPool->recordSuccess($credential);
        } catch (Throwable $exception) {
            $this->credentialPool->recordFailure($credential, 'embedding_or_vector_error');
            Log::warning('Vector retrieval unavailable; using keyword fallback.', [
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'error' => $exception->getMessage(),
            ]);

            return collect();
        }

        $scores = collect($points)->mapWithKeys(fn (array $point): array => [
            (int) data_get($point, 'payload.chunk_id') => (float) ($point['score'] ?? 0),
        ]);

        if ($scores->isEmpty()) {
            return collect();
        }

        $chunks = $this->accessibleChunks($user, $conversation)
            ->whereIn('id', $scores->keys())
            ->get()
            ->sortBy(fn (KnowledgeChunk $chunk): int => (int) $scores->keys()->search($chunk->id));

        return $chunks->map(fn (KnowledgeChunk $chunk): array => [
            'chunk' => $chunk,
            'content' => $chunk->content,
            'score' => (float) $scores->get($chunk->id, 0),
        ])->values();
    }

    /** @return Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}> */
    private function retrieveByKeywords(User $user, Conversation $conversation, string $question, int $limit): Collection
    {
        $terms = collect(preg_split('/[^\pL\pN]+/u', Str::lower($question)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3)
            ->unique()
            ->values();

        if ($terms->isEmpty()) {
            return collect();
        }

        return $this->accessibleChunks($user, $conversation)
            ->limit(200)
            ->get()
            ->map(function (KnowledgeChunk $chunk) use ($terms): array {
                $content = Str::lower($chunk->content);
                $matches = $terms->filter(fn (string $term): bool => str_contains($content, $term))->count();

                return [
                    'chunk' => $chunk,
                    'content' => $chunk->content,
                    'score' => $matches / $terms->count(),
                ];
            })->filter(fn (array $result): bool => $result['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function accessibleChunks(User $user, Conversation $conversation): Builder
    {
        return KnowledgeChunk::query()
            ->with('version.knowledge.course')
            ->whereHas('version.knowledge', function ($query) use ($user, $conversation): void {
                $query->where(function ($visibility) use ($user, $conversation): void {
                    $visibility->where(function ($own) use ($user): void {
                        $own->where('user_id', $user->id)->where('visibility', 'private');
                    })->orWhere(function ($shared) use ($conversation): void {
                        $shared->where('status', 'approved')
                            ->whereIn('visibility', ['course', 'community'])
                            ->when($conversation->course_id, function ($course) use ($conversation): void {
                                $course->where(function ($matching) use ($conversation): void {
                                    $matching->whereNull('course_id')->orWhere('course_id', $conversation->course_id);
                                });
                            });
                    });
                });
            });
    }
}

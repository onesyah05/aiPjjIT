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
    /** @var array<string, array<int, string>> */
    private const TERM_ALIASES = [
        'gorup' => ['gorup', 'grup', 'group'],
        'group' => ['group', 'grup'],
        'gruop' => ['gruop', 'grup', 'group'],
        'grup' => ['grup', 'group'],
        'link' => ['link', 'tautan'],
        'tautan' => ['tautan', 'link'],
        'wa' => ['whatsapp', 'chat.whatsapp.com'],
        'whatapp' => ['whatapp', 'whatsapp', 'chat.whatsapp.com'],
        'whatsap' => ['whatsap', 'whatsapp', 'chat.whatsapp.com'],
        'whatsapp' => ['whatsapp', 'chat.whatsapp.com'],
    ];

    /** @var array<int, string> */
    private const CONVERSATIONAL_TERMS = [
        'aku',
        'dong',
        'mohon',
        'please',
        'saya',
        'tolong',
    ];

    /** @var array<int, string> */
    private const LINK_INTENT_TERMS = [
        'chat.whatsapp.com',
        'gorup',
        'group',
        'gruop',
        'grup',
        'info',
        'link',
        'tautan',
        'wa',
        'whatapp',
        'whatsap',
        'whatsapp',
    ];

    public function __construct(
        private EmbeddingService $embedding,
        private QdrantService $qdrant,
        private CredentialPoolService $credentialPool,
    ) {}

    /** @return Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}> */
    public function retrieve(User $user, Conversation $conversation, string $question, int $limit = 5): Collection
    {
        $candidateLimit = max($limit * 4, 20);
        $vectorResults = $this->retrieveFromVectorStore($user, $conversation, $question, $candidateLimit);
        $keywordResults = $this->retrieveByKeywords($user, $conversation, $question, $candidateLimit);
        $prioritizeWhatsAppLinks = $this->asksForLink($this->keywordTermGroups($question));

        return $this->mergeResults($vectorResults, $keywordResults, $limit, $prioritizeWhatsAppLinks);
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
            ->whereIn('knowledge_chunks.id', $scores->keys())
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
        $termGroups = $this->keywordTermGroups($question);

        if ($termGroups->isEmpty()) {
            return collect();
        }

        $query = $this->accessibleChunks($user, $conversation);
        $searchTerms = $termGroups->flatten()->unique()->values();
        $prioritizeWhatsAppLinks = $this->asksForLink($termGroups);
        $subjectTerms = $this->subjectTerms($termGroups);

        $query->where(function ($q) use ($searchTerms): void {
            foreach ($searchTerms as $term) {
                $q->orWhere('knowledge_chunks.content', 'like', '%'.$term.'%');
            }
        });

        return $query
            ->orderByDesc('knowledge_versions.id')
            ->orderByDesc('knowledge_chunks.id')
            ->limit(200)
            ->get()
            ->map(function (KnowledgeChunk $chunk) use ($prioritizeWhatsAppLinks, $subjectTerms, $termGroups): array {
                $content = Str::lower($chunk->content);
                $matches = $termGroups
                    ->filter(fn (Collection $aliases): bool => $aliases->contains(
                        fn (string $alias): bool => str_contains($content, $alias),
                    ))
                    ->count();
                $score = $matches / $termGroups->count();

                if ($prioritizeWhatsAppLinks && str_contains($content, 'chat.whatsapp.com')) {
                    $score += 0.15;
                    $score += $this->relevantLinkContextScore($content, $subjectTerms) * 0.75;
                }

                return [
                    'chunk' => $chunk,
                    'content' => $chunk->content,
                    'score' => $score,
                ];
            })->filter(fn (array $result): bool => $result['score'] > 0)
            ->sort(function (array $left, array $right) use ($prioritizeWhatsAppLinks): int {
                $scoreComparison = $right['score'] <=> $left['score'];

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                if ($prioritizeWhatsAppLinks) {
                    $linkComparison = str_contains($right['content'], 'chat.whatsapp.com')
                        <=> str_contains($left['content'], 'chat.whatsapp.com');

                    if ($linkComparison !== 0) {
                        return $linkComparison;
                    }
                }

                $versionComparison = $right['chunk']->knowledge_version_id <=> $left['chunk']->knowledge_version_id;

                return $versionComparison !== 0
                    ? $versionComparison
                    : $right['chunk']->id <=> $left['chunk']->id;
            })
            ->take($limit)
            ->values();
    }

    /**
     * @param  Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}>  $vectorResults
     * @param  Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}>  $keywordResults
     * @return Collection<int, array{chunk: KnowledgeChunk, content: string, score: float}>
     */
    private function mergeResults(
        Collection $vectorResults,
        Collection $keywordResults,
        int $limit,
        bool $prioritizeWhatsAppLinks,
    ): Collection {
        return $vectorResults
            ->concat($keywordResults)
            ->groupBy(fn (array $result): int => $result['chunk']->id)
            ->map(function (Collection $matches): array {
                $result = $matches->first();
                $result['score'] = (float) $matches->max('score');

                return $result;
            })
            ->sort(function (array $left, array $right) use ($prioritizeWhatsAppLinks): int {
                $scoreComparison = $right['score'] <=> $left['score'];

                if ($scoreComparison !== 0) {
                    return $scoreComparison;
                }

                if ($prioritizeWhatsAppLinks) {
                    $linkComparison = str_contains($right['content'], 'chat.whatsapp.com')
                        <=> str_contains($left['content'], 'chat.whatsapp.com');

                    if ($linkComparison !== 0) {
                        return $linkComparison;
                    }
                }

                $versionComparison = $right['chunk']->knowledge_version_id <=> $left['chunk']->knowledge_version_id;

                return $versionComparison !== 0
                    ? $versionComparison
                    : $right['chunk']->id <=> $left['chunk']->id;
            })
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, Collection<int, string>> */
    private function keywordTermGroups(string $question): Collection
    {
        return collect(preg_split('/[^\pL\pN.]+/u', Str::lower($question)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 3 || $term === 'wa')
            ->reject(fn (string $term): bool => in_array($term, self::CONVERSATIONAL_TERMS, true))
            ->unique()
            ->map(fn (string $term): Collection => collect(self::TERM_ALIASES[$term] ?? [$term])->unique()->values())
            ->values();
    }

    /** @param Collection<int, Collection<int, string>> $termGroups */
    private function asksForLink(Collection $termGroups): bool
    {
        return $termGroups
            ->flatten()
            ->contains(fn (string $term): bool => in_array($term, [
                'chat.whatsapp.com',
                'group',
                'grup',
                'link',
                'tautan',
                'whatsapp',
            ], true));
    }

    /**
     * @param  Collection<int, Collection<int, string>>  $termGroups
     * @return Collection<int, string>
     */
    private function subjectTerms(Collection $termGroups): Collection
    {
        return $termGroups
            ->reject(fn (Collection $aliases): bool => $aliases->intersect(self::LINK_INTENT_TERMS)->isNotEmpty())
            ->flatten()
            ->unique()
            ->values();
    }

    /** @param Collection<int, string> $subjectTerms */
    private function relevantLinkContextScore(string $content, Collection $subjectTerms): int
    {
        if ($subjectTerms->isEmpty()) {
            return 0;
        }

        $lines = collect(preg_split('/\R/u', $content) ?: [])
            ->map(fn (string $line): string => Str::lower(trim($line)))
            ->filter()
            ->values();

        return (int) $lines->map(function (string $line, int $index) use ($lines, $subjectTerms): int {
            if (! str_contains($line, 'chat.whatsapp.com')) {
                return 0;
            }

            $directMatches = $subjectTerms->filter(
                fn (string $term): bool => str_contains($line, $term),
            )->count();
            $previousLine = $index > 0 ? $lines->get($index - 1, '') : '';
            $previousMatches = $subjectTerms->filter(
                fn (string $term): bool => str_contains($previousLine, $term),
            )->count();

            return ($directMatches * 2) + $previousMatches;
        })->max();
    }

    private function accessibleChunks(User $user, Conversation $conversation): Builder
    {
        return KnowledgeChunk::query()
            ->select('knowledge_chunks.*')
            ->join('knowledge_versions', 'knowledge_versions.id', '=', 'knowledge_chunks.knowledge_version_id')
            ->join('knowledges', 'knowledges.id', '=', 'knowledge_versions.knowledge_id')
            ->with('version.knowledge.course')
            ->whereNull('knowledges.deleted_at')
            ->whereColumn('knowledges.active_version_id', 'knowledge_versions.id')
            ->where('knowledge_versions.status', 'approved')
            ->where('knowledge_versions.processing_status', 'ready')
            ->where(function ($visibility) use ($user, $conversation): void {
                $visibility->where(function ($own) use ($user): void {
                    $own->where('knowledges.user_id', $user->id)
                        ->where('knowledges.visibility', 'private');
                })->orWhere(function ($shared) use ($conversation): void {
                    $shared->where('knowledges.status', 'approved')
                        ->whereIn('knowledges.visibility', ['course', 'community'])
                        ->when($conversation->course_id, function ($course) use ($conversation): void {
                            $course->where(function ($matching) use ($conversation): void {
                                $matching->whereNull('knowledges.course_id')
                                    ->orWhere('knowledges.course_id', $conversation->course_id);
                            });
                        });
                });
            });
    }
}

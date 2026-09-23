<?php

namespace App\Services\Vector;

use App\Models\KnowledgeChunk;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class QdrantService
{
    public function enabled(): bool
    {
        return (bool) config('services.qdrant.enabled');
    }

    /** @param array<int, float> $vector */
    public function upsert(KnowledgeChunk $chunk, array $vector): void
    {
        $this->ensureCollection(count($vector));
        $knowledge = $chunk->version->knowledge;

        $this->client()->put($this->collectionPath().'/points?wait=true', [
            'points' => [[
                'id' => $chunk->vector_external_id,
                'vector' => $vector,
                'payload' => [
                    'chunk_id' => $chunk->id,
                    'knowledge_id' => $knowledge->id,
                    'version_id' => $chunk->knowledge_version_id,
                    'user_id' => $knowledge->user_id,
                    'course_id' => $knowledge->course_id,
                    'visibility' => $knowledge->visibility,
                    'status' => $knowledge->status,
                ],
            ]],
        ])->throw();
    }

    public function deleteKnowledge(int $knowledgeId): void
    {
        if (! $this->enabled()) {
            return;
        }

        $collection = $this->client()->get($this->collectionPath());

        if ($collection->notFound()) {
            return;
        }

        $collection->throw();

        $this->client()->post($this->collectionPath().'/points/delete?wait=true', [
            'filter' => [
                'must' => [[
                    'key' => 'knowledge_id',
                    'match' => ['value' => $knowledgeId],
                ]],
            ],
        ])->throw();
    }

    /**
     * @param  array<int, float>  $vector
     * @return array<int, array{id: string, score: float, payload: array<string, mixed>}>
     */
    public function query(array $vector, User $user, ?int $courseId, int $limit): array
    {
        $sharedMust = [
            ['key' => 'status', 'match' => ['value' => 'approved']],
            ['key' => 'visibility', 'match' => ['any' => ['course', 'community']]],
        ];

        if ($courseId !== null) {
            $sharedMust[] = [
                'should' => [
                    ['key' => 'course_id', 'match' => ['value' => $courseId]],
                    ['is_null' => ['key' => 'course_id']],
                ],
            ];
        }

        $response = $this->client()->post($this->collectionPath().'/points/query', [
            'query' => $vector,
            'filter' => [
                'should' => [
                    ['must' => [
                        ['key' => 'user_id', 'match' => ['value' => $user->id]],
                        ['key' => 'visibility', 'match' => ['value' => 'private']],
                    ]],
                    ['must' => $sharedMust],
                ],
            ],
            'limit' => $limit,
            'score_threshold' => (float) config('services.qdrant.score_threshold', 0.35),
            'with_payload' => true,
        ])->throw();

        return data_get($response->json(), 'result.points', data_get($response->json(), 'result', []));
    }

    private function ensureCollection(int $dimensions): void
    {
        Cache::lock('qdrant-collection-'.$this->collection(), 15)->block(5, function () use ($dimensions): void {
            $response = $this->client()->get($this->collectionPath());

            if ($response->successful()) {
                return;
            }

            if (! $response->notFound()) {
                $response->throw();
            }

            $this->client()->put($this->collectionPath(), [
                'vectors' => ['size' => $dimensions, 'distance' => 'Cosine'],
            ])->throw();
        });
    }

    private function client(): PendingRequest
    {
        $request = Http::baseUrl(rtrim((string) config('services.qdrant.url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(3)
            ->timeout((int) config('services.qdrant.timeout', 10));

        $apiKey = config('services.qdrant.api_key');

        return is_string($apiKey) && $apiKey !== ''
            ? $request->withHeaders(['api-key' => $apiKey])
            : $request;
    }

    private function collectionPath(): string
    {
        return '/collections/'.rawurlencode($this->collection());
    }

    private function collection(): string
    {
        return (string) config('services.qdrant.collection', 'knowledge');
    }
}

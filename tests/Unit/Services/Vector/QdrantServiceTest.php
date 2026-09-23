<?php

namespace Tests\Unit\Services\Vector;

use App\Services\Vector\QdrantService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QdrantServiceTest extends TestCase
{
    public function test_deletes_every_vector_that_belongs_to_a_knowledge(): void
    {
        config()->set('services.qdrant.enabled', true);
        config()->set('services.qdrant.url', 'http://qdrant.test:6333');
        config()->set('services.qdrant.collection', 'knowledge');
        Http::preventStrayRequests();
        Http::fake([
            'http://qdrant.test:6333/collections/knowledge' => Http::response(['result' => ['status' => 'green']]),
            'http://qdrant.test:6333/collections/knowledge/points/delete*' => Http::response(['status' => 'ok']),
        ]);

        app(QdrantService::class)->deleteKnowledge(91);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains($request->url(), '/points/delete')
            && data_get($request->data(), 'filter.must.0.match.value') === 91);
    }

    public function test_skips_vector_deletion_when_the_collection_does_not_exist(): void
    {
        config()->set('services.qdrant.enabled', true);
        config()->set('services.qdrant.url', 'http://qdrant.test:6333');
        config()->set('services.qdrant.collection', 'knowledge');
        Http::preventStrayRequests();
        Http::fake([
            'http://qdrant.test:6333/collections/knowledge' => Http::response([], 404),
        ]);

        app(QdrantService::class)->deleteKnowledge(91);

        Http::assertSentCount(1);
    }
}

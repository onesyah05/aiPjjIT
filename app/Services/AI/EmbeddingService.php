<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    public function __construct(private VoyageService $voyage) {}

    /**
     * Embed a single text with the given provider. The provider must match the
     * one used to build the Qdrant collection — vectors from different
     * providers are not comparable.
     *
     * @return array<int, float>
     */
    public function embed(string $provider, string $secret, string $content, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        if ($provider === 'voyage') {
            return $this->voyage->embed($secret, $content, $taskType === 'RETRIEVAL_QUERY' ? 'query' : 'document');
        }

        return $this->embedGemini($secret, $content, $taskType);
    }

    /**
     * Embed many texts in as few provider calls as possible. Returns vectors
     * in the same order as the given texts.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>
     */
    public function embedBatch(string $provider, string $secret, array $texts, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        if ($provider === 'voyage') {
            return $this->voyage->embedBatch($secret, $texts, $taskType === 'RETRIEVAL_QUERY' ? 'query' : 'document');
        }

        $vectors = [];

        foreach ($texts as $text) {
            $vectors[] = $this->embedGemini($secret, $text, $taskType);
        }

        return $vectors;
    }

    /** @return array<int, float> */
    private function embedGemini(string $secret, string $content, string $taskType): array
    {
        $model = (string) config('services.gemini.embedding_model');
        $payload = [
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => $content]]],
            'output_dimensionality' => (int) config('services.gemini.embedding_dimensions', 768),
        ];

        if ($model === 'gemini-embedding-001') {
            $payload['taskType'] = $taskType;
        }

        $response = Http::baseUrl(rtrim((string) config('services.gemini.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.gemini.timeout', 60))
            ->withHeaders(['x-goog-api-key' => $secret])
            ->post("models/{$model}:embedContent", $payload)
            ->throw();

        $values = data_get($response->json(), 'embedding.values')
            ?? data_get($response->json(), 'embeddings.0.values');

        if (! is_array($values) || $values === []) {
            throw new RuntimeException('Provider tidak mengembalikan embedding yang valid.');
        }

        return array_map(static fn (mixed $value): float => (float) $value, $values);
    }
}

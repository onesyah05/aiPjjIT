<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class EmbeddingService
{
    /** @return array<int, float> */
    public function embed(string $secret, string $content, string $taskType = 'RETRIEVAL_DOCUMENT'): array
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

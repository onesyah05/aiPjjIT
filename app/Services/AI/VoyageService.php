<?php

namespace App\Services\AI;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class VoyageService
{
    /**
     * Embed one or many texts in a single API call. Voyage accepts up to 1000
     * texts per request, which keeps donated free-tier keys well inside their
     * request-per-minute limits during knowledge re-indexing.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>> Vectors in the same order as $texts.
     */
    public function embedBatch(string $secret, array $texts, string $inputType = 'document'): array
    {
        $response = $this->client($secret)
            ->post('embeddings', [
                'model' => (string) config('services.voyage.model', 'voyage-3.5'),
                'input' => array_values($texts),
                'input_type' => $inputType,
            ])
            ->throw();

        /** @var array<int, array{embedding?: mixed}> $data */
        $data = $response->json('data');

        if (! is_array($data) || count($data) !== count($texts)) {
            throw new RuntimeException('Voyage tidak mengembalikan embedding yang lengkap.');
        }

        $vectors = [];
        foreach ($data as $item) {
            $values = $item['embedding'] ?? null;

            if (! is_array($values) || $values === []) {
                throw new RuntimeException('Voyage tidak mengembalikan embedding yang valid.');
            }

            $vectors[] = array_map(static fn (mixed $value): float => (float) $value, $values);
        }

        return $vectors;
    }

    /** @return array<int, float> */
    public function embed(string $secret, string $text, string $inputType = 'document'): array
    {
        return $this->embedBatch($secret, [$text], $inputType)[0];
    }

    public function validateCredential(string $secret): bool
    {
        try {
            return $this->client($secret)->get('models')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function client(string $secret): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.voyage.base_url', 'https://api.voyageai.com/v1'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.voyage.timeout', 60))
            ->withHeaders(['Authorization' => 'Bearer '.$secret]);
    }
}

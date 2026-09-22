<?php

namespace App\Services\AI;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiService
{
    public function generate(string $secret, string $prompt): string
    {
        $model = config('services.gemini.model');
        $response = $this->client($secret)->post("models/{$model}:generateContent", [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.25,
                'maxOutputTokens' => 2048,
            ],
        ]);

        $response->throw();

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    /** @return Generator<int, string> */
    public function stream(string $secret, string $prompt): Generator
    {
        $model = config('services.gemini.model');
        $response = $this->client($secret)
            ->withOptions(['stream' => true])
            ->post("models/{$model}:streamGenerateContent?alt=sse", $this->payload($prompt));

        $response->throw();

        if (! str_contains(mb_strtolower($response->header('Content-Type')), 'text/event-stream')) {
            $content = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');

            if ($content !== '') {
                yield $content;
            }

            return;
        }

        $body = $response->toPsrResponse()->getBody();
        $buffer = '';

        while (! $body->eof()) {
            $buffer .= $body->read(8192);
            $events = preg_split("/\r?\n\r?\n/", $buffer) ?: [];
            $buffer = array_pop($events) ?? '';

            foreach ($events as $event) {
                $content = $this->contentFromEvent($event);

                if ($content !== '') {
                    yield $content;
                }
            }
        }

        if ($buffer !== '') {
            $content = $this->contentFromEvent($buffer);

            if ($content !== '') {
                yield $content;
            }
        }
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
        return Http::baseUrl(rtrim((string) config('services.gemini.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout((int) config('services.gemini.timeout', 60))
            ->withHeaders(['x-goog-api-key' => $secret]);
    }

    /** @return array<string, mixed> */
    private function payload(string $prompt): array
    {
        return [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature' => 0.25,
                'maxOutputTokens' => 2048,
            ],
        ];
    }

    private function contentFromEvent(string $event): string
    {
        $data = collect(preg_split('/\r?\n/', $event) ?: [])
            ->filter(fn (string $line): bool => str_starts_with($line, 'data:'))
            ->map(fn (string $line): string => trim(substr($line, 5)))
            ->join('');

        if ($data === '' || $data === '[DONE]') {
            return '';
        }

        $decoded = json_decode($data, true);

        return is_array($decoded)
            ? (string) data_get($decoded, 'candidates.0.content.parts.0.text', '')
            : '';
    }
}

<?php

namespace App\Services\AI;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Server-configured chat provider speaking the OpenAI-compatible
 * `/chat/completions` protocol (Dahl, SenseNova, OpenRouter, …).
 */
class ChatCompletionService
{
    private const PAUSE_CACHE_KEY = 'chat_api:paused';

    public function isAvailable(): bool
    {
        return $this->apiKey() !== '' && ! Cache::has(self::PAUSE_CACHE_KEY);
    }

    public function providerName(): string
    {
        return (string) config('services.chat_api.provider', 'openai_compatible');
    }

    /** @return array<int, string> */
    public function models(): array
    {
        return array_values(array_unique(array_filter(array_merge(
            [(string) config('services.chat_api.model')],
            (array) config('services.chat_api.fallback_models', []),
        ))));
    }

    /**
     * Skip the provider for a while so every chat does not wait on a key
     * that is out of quota or rejected.
     */
    public function pause(int $seconds): void
    {
        Cache::put(self::PAUSE_CACHE_KEY, true, now()->addSeconds($seconds));
    }

    /** @return Generator<int, string> */
    public function stream(string $prompt, string $model): Generator
    {
        $response = $this->client()
            ->withOptions(['stream' => true])
            ->post('chat/completions', [
                'model' => $model,
                'stream' => true,
                'temperature' => (float) config('services.chat_api.temperature', 0.25),
                'max_tokens' => (int) config('services.chat_api.max_tokens', 8192),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        $response->throw();

        $filter = new ReasoningTagFilter;

        if (! str_contains(mb_strtolower($response->header('Content-Type')), 'text/event-stream')) {
            $this->assertNoError($response->json());
            $this->assertNaturalFinish((string) data_get($response->json(), 'choices.0.finish_reason', 'stop'));
            $content = $filter->push((string) data_get($response->json(), 'choices.0.message.content', '')).$filter->flush();

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
                $content = $filter->push($this->contentFromEvent($event));

                if ($content !== '') {
                    yield $content;
                }
            }
        }

        $content = $filter->push($this->contentFromEvent($buffer)).$filter->flush();

        if ($content !== '') {
            yield $content;
        }
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.chat_api.base_url'), '/'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout((int) config('services.chat_api.timeout', 90))
            ->withToken($this->apiKey());
    }

    private function apiKey(): string
    {
        return (string) config('services.chat_api.api_key');
    }

    /**
     * Returns only the answer text of one SSE event; `reasoning` /
     * `reasoning_content` deltas are the model's hidden thinking.
     */
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

        if (! is_array($decoded)) {
            return '';
        }

        $this->assertNoError($decoded);
        $this->assertNaturalFinish((string) data_get($decoded, 'choices.0.finish_reason', ''));

        return (string) data_get($decoded, 'choices.0.delta.content', '');
    }

    private function assertNoError(mixed $payload): void
    {
        $error = data_get($payload, 'error');

        if ($error !== null) {
            throw new RuntimeException('Provider mengembalikan error: '.(data_get($error, 'message') ?? json_encode($error)));
        }
    }

    /**
     * A content-filter stop truncates the answer; treat it as a provider
     * failure so the caller falls back instead of sending a cut-off reply.
     */
    private function assertNaturalFinish(string $finishReason): void
    {
        if (in_array($finishReason, ['', 'stop', 'length', 'eos', 'end_turn'], true)) {
            return;
        }

        throw new RuntimeException("Provider menghentikan jawaban lebih awal (finish_reason: {$finishReason}).");
    }
}

<?php

namespace App\Services\Chat;

use App\Models\AiRequestLog;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\AI\GeminiService;
use App\Services\CredentialPoolService;
use App\Services\Knowledge\KnowledgeLinkExtractor;
use App\Services\Knowledge\RetrievalService;
use App\Services\PromptBuilderService;
use Generator;
use Illuminate\Http\Client\RequestException;
use RuntimeException;
use Throwable;

class ChatService
{
    public function __construct(
        private RetrievalService $retrievalService,
        private PromptBuilderService $promptBuilder,
        private CredentialPoolService $credentialPool,
        private GeminiService $gemini,
        private KnowledgeLinkExtractor $knowledgeLinkExtractor,
    ) {}

    /** @return Generator<int, array{content?: string, sources?: array<int, array<string, mixed>>, message_id?: int}> */
    public function stream(Conversation $conversation, string $content, ?string $clientRequestId = null): Generator
    {
        if ($clientRequestId !== null) {
            $existing = $conversation->messages()
                ->where('client_request_id', $clientRequestId)
                ->where('role', 'user')
                ->first();

            if ($existing !== null) {
                $assistant = $conversation->messages()
                    ->with('sources.chunk.version.knowledge:id,title')
                    ->where('role', 'assistant')
                    ->where('id', '>', $existing->id)
                    ->oldest('id')
                    ->first();

                if ($assistant !== null) {
                    yield ['content' => $assistant->content];
                    yield ['sources' => $this->serializeSources($assistant), 'message_id' => $assistant->id];
                }

                return;
            }
        }

        $userMessage = $conversation->messages()->create([
            'client_request_id' => $clientRequestId,
            'role' => 'user',
            'content' => $content,
            'status' => 'completed',
        ]);

        $conversation->update([
            'title' => $conversation->title ?: mb_substr($content, 0, 80),
            'last_message_at' => now(),
        ]);

        $retrieved = $this->retrievalService->retrieve($conversation->user, $conversation, $content);
        $sourceLinks = $this->sourceLinks($retrieved->all());
        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => '',
            'status' => 'streaming',
        ]);

        if ($conversation->mode === 'knowledge_only' && $retrieved->isEmpty()) {
            $fallback = 'Informasi tersebut belum ditemukan pada knowledge yang tersedia.';
            $assistant->update(['content' => $fallback, 'status' => 'completed']);
            foreach (['Informasi tersebut ', 'belum', ' ', 'ditemukan', ' pada knowledge yang tersedia.'] as $chunk) {
                yield ['content' => $chunk];
            }

            return;
        }

        $recentMessages = $conversation->messages()
            ->whereKeyNot($userMessage->id)
            ->latest('id')
            ->limit(12)
            ->get(['role', 'content'])
            ->reverse()
            ->values()
            ->toArray();

        $prompt = $this->promptBuilder->build(
            $content,
            $retrieved->map(fn (array $result): array => [
                'content' => $result['content'],
                'score' => $result['score'],
                'links' => $this->knowledgeLinkExtractor->extract(
                    $result['chunk']->version?->content ?? $result['content'],
                ),
            ])->all(),
            $conversation->mode,
            $recentMessages,
        );

        $credentials = $this->credentialPool->getAvailableCredentials(
            (int) config('services.gemini.max_attempts', 3),
        );

        if ($credentials->isEmpty()) {
            $assistant->update(['status' => 'failed', 'error_code' => 'credentials_unavailable']);
            AiRequestLog::query()->create([
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
                'message_id' => $assistant->id,
                'operation' => 'chat',
                'provider' => 'gemini',
                'model' => config('services.gemini.model'),
                'status' => 'failed',
                'error_code' => 'credentials_unavailable',
                'retrieved_chunks' => $retrieved->count(),
            ]);
            throw new RuntimeException('Tidak ada credential AI yang tersedia.');
        }

        foreach ($credentials as $credential) {
            $startedAt = hrtime(true);
            $generatedContent = '';
            $this->credentialPool->recordAttempt($credential);
            $log = AiRequestLog::query()->create([
                'user_id' => $conversation->user_id,
                'conversation_id' => $conversation->id,
                'message_id' => $assistant->id,
                'credential_id' => $credential->id,
                'operation' => 'chat',
                'provider' => $credential->provider,
                'model' => config('services.gemini.model'),
                'status' => 'streaming',
                'retrieved_chunks' => $retrieved->count(),
            ]);

            try {
                foreach ($this->gemini->stream($credential->encrypted_secret, $prompt) as $chunk) {
                    $generatedContent .= $chunk;
                    yield ['content' => $chunk];
                }

                if ($generatedContent === '') {
                    throw new RuntimeException('Provider mengembalikan respons kosong.');
                }

                $linkAppendix = $this->knowledgeLinkExtractor->appendixFor($generatedContent, $sourceLinks);

                if ($linkAppendix !== '') {
                    $generatedContent .= $linkAppendix;
                    yield ['content' => $linkAppendix];
                }

                $this->credentialPool->recordSuccess($credential);
                $latency = (int) round((hrtime(true) - $startedAt) / 1_000_000);
                $assistant->update([
                    'content' => $generatedContent,
                    'status' => 'completed',
                    'provider' => $credential->provider,
                    'model' => config('services.gemini.model'),
                    'latency_ms' => $latency,
                    'error_code' => null,
                ]);
                $sources = $this->persistSources($assistant, $retrieved->all());
                $log->update(['status' => 'completed', 'latency_ms' => $latency]);
                yield ['sources' => $sources, 'message_id' => $assistant->id];

                return;
            } catch (RequestException $exception) {
                $status = $exception->response->status();
                $errorCode = $status === 429 ? 'rate_limited' : "http_{$status}";
                $this->credentialPool->recordFailure($credential, $errorCode);
                $log->update([
                    'status' => 'failed',
                    'error_code' => $errorCode,
                    'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                ]);

                if ($status === 429) {
                    $this->credentialPool->markRateLimitHit($credential);
                } elseif (in_array($status, [401, 403], true)) {
                    $this->credentialPool->markInvalid(
                        $credential,
                        $errorCode,
                        'Credential ditolak oleh Gemini. Periksa key dan izin project sebelum mengaktifkannya kembali.',
                    );
                }
            } catch (Throwable $exception) {
                $this->credentialPool->recordFailure($credential, 'provider_error');
                $log->update([
                    'status' => 'failed',
                    'error_code' => 'provider_error',
                    'latency_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
                ]);
            }

            if ($generatedContent !== '') {
                $assistant->update([
                    'content' => $generatedContent,
                    'status' => 'failed',
                    'error_code' => 'stream_interrupted',
                ]);

                throw new RuntimeException('Streaming jawaban terputus. Sebagian jawaban tetap disimpan.');
            }
        }

        $assistant->update(['status' => 'failed', 'error_code' => 'credentials_unavailable']);
        throw new RuntimeException('Semua credential AI sedang tidak tersedia.');
    }

    /**
     * @param  array<int, array{chunk: mixed, content: string, score: float}>  $retrieved
     * @return array<int, array<string, mixed>>
     */
    private function persistSources(
        Message $message,
        array $retrieved,
    ): array {
        $sources = [];

        foreach ($retrieved as $rank => $result) {
            $chunk = $result['chunk'];
            $message->sources()->create([
                'knowledge_chunk_id' => $chunk->id,
                'knowledge_version_id' => $chunk->knowledge_version_id,
                'rank' => $rank + 1,
                'similarity_score' => $result['score'],
            ]);

            $sources[] = [
                'id' => $chunk->id,
                'knowledge_id' => $chunk->version->knowledge->id,
                'title' => $chunk->version->knowledge->title,
                'heading' => $chunk->heading_path,
                'score' => $result['score'],
                'links' => $this->knowledgeLinkExtractor->extract(
                    $chunk->version?->content ?? $chunk->content,
                ),
            ];
        }

        return $sources;
    }

    /** @return array<int, array<string, mixed>> */
    private function serializeSources(Message $message): array
    {
        return $message->sources->map(fn ($source): array => [
            'id' => $source->knowledge_chunk_id,
            'knowledge_id' => $source->chunk?->version?->knowledge?->id,
            'title' => $source->chunk?->version?->knowledge?->title,
            'heading' => $source->chunk?->heading_path,
            'score' => $source->similarity_score,
            'links' => $this->knowledgeLinkExtractor->extract(
                $source->chunk?->version?->content ?? $source->chunk?->content ?? '',
            ),
        ])->all();
    }

    /**
     * @param  array<int, array{chunk: mixed, content: string, score: float}>  $retrieved
     * @return array<int, array{url: string, label: string}>
     */
    private function sourceLinks(array $retrieved): array
    {
        return collect($retrieved)
            ->flatMap(fn (array $result): array => $this->knowledgeLinkExtractor->extract(
                $result['chunk']->version?->content ?? $result['content'],
            ))
            ->unique('url')
            ->take(8)
            ->values()
            ->all();
    }
}

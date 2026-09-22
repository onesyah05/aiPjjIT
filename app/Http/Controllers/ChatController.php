<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Services\Chat\ChatService;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ChatController extends Controller
{
    public function __construct(
        private ChatService $chatService,
    ) {}

    public function stream(SendMessageRequest $request, Conversation $conversation): StreamedResponse
    {
        return new StreamedResponse(function () use ($request, $conversation): void {
            try {
                foreach ($this->chatService->stream(
                    $conversation,
                    $request->validated('message'),
                    $request->validated('client_request_id'),
                ) as $event) {
                    echo 'data: '.json_encode($event, JSON_UNESCAPED_UNICODE)."\n\n";
                    $this->flushOutput();
                }
            } catch (RuntimeException $exception) {
                echo 'event: error'."\n";
                echo 'data: '.json_encode(['message' => $exception->getMessage()], JSON_UNESCAPED_UNICODE)."\n\n";
            } catch (Throwable $exception) {
                report($exception);
                echo 'event: error'."\n";
                echo 'data: '.json_encode(['message' => 'Terjadi kesalahan saat memproses jawaban.'], JSON_UNESCAPED_UNICODE)."\n\n";
            }

            echo "data: [DONE]\n\n";
            $this->flushOutput();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function flushOutput(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}

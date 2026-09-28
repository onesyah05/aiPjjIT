<?php

namespace Tests\Feature\Jobs;

use App\Jobs\HandleDiscordMention;
use App\Models\Conversation;
use App\Models\DiscordBotConversation;
use App\Models\User;
use App\Services\Chat\ChatService;
use Generator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class HandleDiscordMentionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sends_the_web_chat_service_answer_to_discord(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/v10/channels/channel-1/typing' => Http::response(status: 204),
            'https://discord.com/api/v10/channels/channel-1/messages/placeholder-1' => Http::response(),
            'https://discord.com/api/v10/channels/channel-1/messages' => Http::response(['id' => 'placeholder-1']),
        ]);
        config()->set('services.discord.bot_token', 'discord-test-token');
        $user = User::factory()->create([
            'email' => 'student@example.com',
        ]);
        $user->discordAccount()->create([
            'discord_user_id' => 'user-1',
            'username' => 'student',
            'access_token_encrypted' => 'discord-access-token',
        ]);
        $answer = <<<'MARKDOWN'
Berikut informasi lengkap AIK 1:

- Grup: https://chat.whatsapp.com/valid-aik-link
- Tatap maya minggu pertama ditunda dan dijadwalkan mulai minggu kedua.

![Bukti jadwal](https://cdn.discordapp.com/aik-schedule.png)
MARKDOWN;
        $chatService = Mockery::mock(ChatService::class);
        $chatService->shouldReceive('stream')
            ->once()
            ->withArgs(fn (Conversation $conversation, string $question, string $clientRequestId): bool => $conversation->user_id === $user->id
                && $question === 'info group AIK'
                && $clientRequestId === 'discord:message-1')
            ->andReturn($this->streamingResponse($answer));
        $job = new HandleDiscordMention(
            channelId: 'channel-1',
            messageId: 'message-1',
            userId: 'user-1',
            username: 'student',
            question: 'info group AIK',
        );

        $job->handle($chatService);

        $discordConversation = DiscordBotConversation::query()->sole();
        $this->assertSame('completed', $discordConversation->status);
        $this->assertSame($answer, $discordConversation->answer);
        $webConversation = Conversation::query()->sole();
        $this->assertSame($user->id, $webConversation->user_id);
        $this->assertSame('[Discord] info group AIK', $webConversation->title);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_contains((string) $request['content'], 'https://chat.whatsapp.com/valid-aik-link')
            && str_contains((string) $request['content'], 'Tatap maya minggu pertama ditunda')
            && ! str_contains((string) $request['content'], 'cdn.discordapp.com/aik-schedule.png'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && ($request['content'] ?? null) === 'https://cdn.discordapp.com/aik-schedule.png');
    }

    /** @return Generator<int, array{content: string}> */
    private function streamingResponse(string $content): Generator
    {
        yield ['content' => $content];
    }
}

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
        $this->assertSame('[Discord] student', $webConversation->title);
        $this->assertSame($webConversation->id, $discordConversation->conversation_id);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_contains((string) $request['content'], 'https://chat.whatsapp.com/valid-aik-link')
            && str_contains((string) $request['content'], 'Tatap maya minggu pertama ditunda')
            && ! str_contains((string) $request['content'], 'cdn.discordapp.com/aik-schedule.png'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && ($request['content'] ?? null) === 'https://cdn.discordapp.com/aik-schedule.png');
    }

    public function test_retries_a_message_with_an_existing_failed_record(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/v10/channels/channel-1/typing' => Http::response(status: 204),
            'https://discord.com/api/v10/channels/channel-1/messages/placeholder-1' => Http::response(),
            'https://discord.com/api/v10/channels/channel-1/messages' => Http::response(['id' => 'placeholder-1']),
        ]);
        config()->set('services.discord.bot_token', 'discord-test-token');
        $user = User::factory()->create(['email' => 'discord-bot@pjj.ai']);
        DiscordBotConversation::query()->create([
            'discord_channel_id' => 'channel-1',
            'discord_user_id' => 'user-1',
            'discord_message_id' => 'message-1',
            'discord_username' => 'student',
            'question' => 'info group AIK',
            'status' => 'failed',
            'error_code' => 'exception',
        ]);
        $chatService = Mockery::mock(ChatService::class);
        $chatService->shouldReceive('stream')
            ->once()
            ->withArgs(fn (Conversation $conversation): bool => $conversation->user_id === $user->id)
            ->andReturn($this->streamingResponse('Jawaban terbaru dari web chat.'));
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
        $this->assertSame('Jawaban terbaru dari web chat.', $discordConversation->answer);
        $this->assertNull($discordConversation->error_code);
    }

    public function test_posts_the_answer_when_the_placeholder_cannot_be_edited(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/v10/channels/channel-1/typing' => Http::response(status: 204),
            'https://discord.com/api/v10/channels/channel-1/messages/placeholder-1' => Http::response(status: 500),
            'https://discord.com/api/v10/channels/channel-1/messages' => Http::sequence()
                ->push(['id' => 'placeholder-1'])
                ->push(['id' => 'answer-1']),
        ]);
        config()->set('services.discord.bot_token', 'discord-test-token');
        User::factory()->create(['email' => 'discord-bot@pjj.ai']);
        $chatService = Mockery::mock(ChatService::class);
        $chatService->shouldReceive('stream')
            ->once()
            ->andReturn($this->streamingResponse('Jawaban tetap terkirim.'));
        $job = new HandleDiscordMention(
            channelId: 'channel-1',
            messageId: 'message-1',
            userId: 'user-1',
            username: 'student',
            question: 'info group AIK',
        );

        $job->handle($chatService);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && ($request['content'] ?? null) === '<@user-1>'."\n\n".'Jawaban tetap terkirim.');
    }

    public function test_marks_the_record_failed_and_rethrows_the_exception_for_a_queue_retry(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/v10/channels/channel-1/typing' => Http::response(status: 204),
            'https://discord.com/api/v10/channels/channel-1/messages/placeholder-1' => Http::response(),
            'https://discord.com/api/v10/channels/channel-1/messages' => Http::response(['id' => 'placeholder-1']),
        ]);
        config()->set('services.discord.bot_token', 'discord-test-token');
        User::factory()->create(['email' => 'discord-bot@pjj.ai']);
        $chatService = Mockery::mock(ChatService::class);
        $chatService->shouldReceive('stream')
            ->once()
            ->andThrow(new \RuntimeException('AI provider unavailable'));
        $job = new HandleDiscordMention(
            channelId: 'channel-1',
            messageId: 'message-1',
            userId: 'user-1',
            username: 'student',
            question: 'info group AIK',
        );

        try {
            $job->handle($chatService);
            $this->fail('The failed Discord job did not rethrow its exception.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('AI provider unavailable', $exception->getMessage());
        }

        $discordConversation = DiscordBotConversation::query()->sole();
        $this->assertSame('failed', $discordConversation->status);
        $this->assertSame('exception', $discordConversation->error_code);
    }

    public function test_reuses_one_conversation_per_discord_user(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/v10/channels/channel-1/typing' => Http::response(status: 204),
            'https://discord.com/api/v10/channels/channel-1/messages/placeholder-1' => Http::response(),
            'https://discord.com/api/v10/channels/channel-1/messages' => Http::response(['id' => 'placeholder-1']),
        ]);
        config()->set('services.discord.bot_token', 'discord-test-token');
        User::factory()->create(['email' => 'discord-bot@pjj.ai']);
        $chatService = Mockery::mock(ChatService::class);
        $chatService->shouldReceive('stream')
            ->twice()
            ->andReturnUsing(fn (): Generator => $this->streamingResponse('Jawaban.'));

        $first = new HandleDiscordMention(
            channelId: 'channel-1',
            messageId: 'message-1',
            userId: 'user-1',
            username: 'student',
            question: 'pertanyaan pertama',
        );
        $first->handle($chatService);

        $second = new HandleDiscordMention(
            channelId: 'channel-1',
            messageId: 'message-2',
            userId: 'user-1',
            username: 'student',
            question: 'pertanyaan kedua',
        );
        $second->handle($chatService);

        $webConversation = Conversation::query()->sole();
        $this->assertSame('[Discord] student', $webConversation->title);
        $this->assertSame(
            [$webConversation->id, $webConversation->id],
            DiscordBotConversation::query()->orderBy('id')->pluck('conversation_id')->all(),
        );
    }

    /** @return Generator<int, array{content: string}> */
    private function streamingResponse(string $content): Generator
    {
        yield ['content' => $content];
    }
}

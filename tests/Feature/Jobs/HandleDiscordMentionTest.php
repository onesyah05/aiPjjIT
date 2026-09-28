<?php

namespace Tests\Feature\Jobs;

use App\Jobs\HandleDiscordMention;
use App\Models\AiCredential;
use App\Models\DiscordBotConversation;
use App\Models\Knowledge;
use App\Models\User;
use App\Services\AI\GeminiService;
use App\Services\CredentialPoolService;
use App\Services\Knowledge\KnowledgeLinkExtractor;
use App\Services\Knowledge\RetrievalService;
use App\Services\PromptBuilderService;
use Generator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class HandleDiscordMentionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_appends_a_retrieved_link_when_the_model_omits_it(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/v10/channels/channel-1/typing' => Http::response(status: 204),
            'https://discord.com/api/v10/channels/channel-1/messages/placeholder-1' => Http::response(),
            'https://discord.com/api/v10/channels/channel-1/messages' => Http::response(['id' => 'placeholder-1']),
        ]);
        config()->set('services.discord.bot_token', 'discord-test-token');
        $user = User::factory()->create([
            'email' => 'discord-bot@pjj.ai',
        ]);
        $knowledge = Knowledge::query()->create([
            'user_id' => $user->id,
            'title' => 'Grup Mata Kuliah AIK',
            'visibility' => 'community',
            'status' => 'approved',
        ]);
        $version = $knowledge->versions()->create([
            'version' => 1,
            'content' => 'Grup AIK: https://chat.whatsapp.com/valid-aik-link',
            'source_type' => 'discord',
            'status' => 'approved',
            'processing_status' => 'ready',
        ]);
        $chunk = $version->chunks()->create([
            'chunk_index' => 0,
            'content' => $version->content,
            'token_count' => 5,
        ])->load('version');
        $knowledge->update(['active_version_id' => $version->id]);
        $credential = new AiCredential;
        $credential->setRawAttributes([
            'id' => 1,
            'provider' => 'gemini',
            'encrypted_secret' => Crypt::encryptString('gemini-test-secret'),
        ]);
        $retrieved = collect([[
            'chunk' => $chunk,
            'content' => $chunk->content,
            'score' => 1.0,
        ]]);
        $retrievalService = Mockery::mock(RetrievalService::class);
        $retrievalService->shouldReceive('retrieve')->once()->andReturn($retrieved);
        $promptBuilder = Mockery::mock(PromptBuilderService::class);
        $promptBuilder->shouldReceive('build')->once()->andReturn('prompt');
        $credentialPool = Mockery::mock(CredentialPoolService::class);
        $credentialPool->shouldReceive('getAvailableCredentials')->once()
            ->andReturn(new Collection([$credential]));
        $credentialPool->shouldReceive('recordAttempt')->once()->with($credential);
        $credentialPool->shouldReceive('recordSuccess')->once()->with($credential);
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('stream')->once()->andReturn($this->streamingResponse('Jawaban dari sumber.'));
        $job = new HandleDiscordMention(
            channelId: 'channel-1',
            messageId: 'message-1',
            userId: 'user-1',
            username: 'student',
            question: 'info gorup AIK dong',
        );

        $job->handle(
            $gemini,
            $credentialPool,
            $promptBuilder,
            $retrievalService,
            new KnowledgeLinkExtractor,
        );

        $conversation = DiscordBotConversation::query()->sole();
        $this->assertSame('completed', $conversation->status);
        $this->assertStringContainsString('https://chat.whatsapp.com/valid-aik-link', $conversation->answer);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_contains((string) $request['content'], 'https://chat.whatsapp.com/valid-aik-link'));
    }

    /** @return Generator<int, string> */
    private function streamingResponse(string $content): Generator
    {
        yield $content;
    }
}

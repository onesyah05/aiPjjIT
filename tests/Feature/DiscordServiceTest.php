<?php

namespace Tests\Feature;

use App\Services\DiscordService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordServiceTest extends TestCase
{
    public function test_archived_threads_follow_pagination_until_discord_reports_no_more(): void
    {
        config(['services.discord.bot_token' => 'bot-token']);
        Http::preventStrayRequests();
        Http::fake([
            'discord.com/api/v10/channels/forum-1/threads/archived/public*' => Http::sequence()
                ->push([
                    'threads' => [
                        ['id' => 't1', 'thread_metadata' => ['archive_timestamp' => '2026-11-02T00:00:00+00:00']],
                        ['id' => 't2', 'thread_metadata' => ['archive_timestamp' => '2026-11-01T00:00:00+00:00']],
                    ],
                    'has_more' => true,
                ])
                ->push([
                    'threads' => [['id' => 't3', 'thread_metadata' => ['archive_timestamp' => '2026-10-01T00:00:00+00:00']]],
                    'has_more' => false,
                ]),
        ]);

        $threads = app(DiscordService::class)->getArchivedThreads('forum-1');

        $this->assertSame(['t1', 't2', 't3'], array_column($threads, 'id'));
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => ($request->data()['before'] ?? null) === '2026-11-01T00:00:00+00:00');
    }
}

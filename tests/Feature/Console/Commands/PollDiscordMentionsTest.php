<?php

namespace Tests\Feature\Console\Commands;

use App\Jobs\HandleDiscordMention;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollDiscordMentionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_mention_is_sent_to_the_queue_instead_of_running_inside_the_polling_daemon(): void
    {
        Queue::fake([HandleDiscordMention::class]);
        Http::preventStrayRequests();
        Http::fake([
            'https://discord.com/api/v10/channels/channel-1/messages*' => Http::response([[
                'id' => 'message-1',
                'channel_id' => 'channel-1',
                'author' => [
                    'id' => 'user-1',
                    'username' => 'student',
                    'bot' => false,
                ],
                'content' => '<@1545329070315802675> info gorup AIK dong',
                'mentions' => [['id' => '1545329070315802675']],
            ]]),
        ]);
        config()->set('services.discord.bot_token', 'discord-test-token');
        config()->set('services.discord.bot_channel_id', 'channel-1');
        Cache::forget('discord_poll_last_message_channel-1');

        $this->artisan('discord:poll-mentions')->assertSuccessful();

        Queue::assertPushed(HandleDiscordMention::class, fn (HandleDiscordMention $job): bool => $job->connection !== 'sync'
            && $job->channelId === 'channel-1'
            && $job->messageId === 'message-1'
            && $job->question === 'info gorup AIK dong');
    }
}

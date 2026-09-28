<?php

namespace Tests\Feature\Http\Controllers;

use App\Jobs\HandleDiscordMention;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DiscordWebhookControllerTest extends TestCase
{
    public function test_configured_channel_mention_dispatches_a_reply_job(): void
    {
        Queue::fake([HandleDiscordMention::class]);
        config()->set('services.discord.bot_user_id', 'bot-123');
        config()->set('services.discord.bot_channel_id', 'active-channel');
        config()->set('services.discord.channel_ids', []);

        $response = $this->postJson(route('discord.webhook'), [
            'id' => 'message-123',
            'channel_id' => 'active-channel',
            'author' => [
                'id' => 'student-123',
                'username' => 'student',
                'bot' => false,
            ],
            'content' => '<@bot-123> info group AIK',
            'mentions' => [['id' => 'bot-123']],
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
        Queue::assertPushed(HandleDiscordMention::class, fn (HandleDiscordMention $job): bool => $job->channelId === 'active-channel'
            && $job->messageId === 'message-123'
            && $job->userId === 'student-123'
            && $job->question === 'info group AIK');
    }
}

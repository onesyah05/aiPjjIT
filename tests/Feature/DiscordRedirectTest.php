<?php

namespace Tests\Feature;

use Tests\TestCase;

class DiscordRedirectTest extends TestCase
{
    public function test_guest_can_be_redirected_to_discord_oauth(): void
    {
        config([
            'services.discord.client_id' => 'discord-client-id',
            'services.discord.client_secret' => 'discord-client-secret',
            'services.discord.redirect' => 'http://localhost/auth/discord/callback',
        ]);

        $this->get(route('discord.login'))
            ->assertRedirectContains('discord.com/api/oauth2/authorize')
            ->assertRedirectContains('client_id=discord-client-id')
            ->assertRedirectContains('guilds.members.read');
    }
}

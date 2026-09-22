<?php

namespace Tests\Feature;

use App\Exceptions\DiscordGuildMembershipException;
use App\Services\DiscordOAuthService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class DiscordAuthenticationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_user_only_after_discord_guild_membership_is_verified(): void
    {
        $accessToken = 'discord-access-token-'.str_repeat('a', 180);
        $refreshToken = 'discord-refresh-token-'.str_repeat('b', 180);

        config(['services.discord.guild_id' => 'guild-123']);
        Http::preventStrayRequests();
        Http::fake([
            'discord.com/api/v10/users/@me/guilds/guild-123/member' => Http::response(['roles' => ['student-role']]),
        ]);
        $discordUser = SocialiteUser::fake([
            'id' => 'discord-123',
            'name' => 'mahasiswa',
            'email' => null,
            'global_name' => 'Mahasiswa PJJ',
            'avatar' => 'avatar-hash',
            'token' => $accessToken,
            'refreshToken' => $refreshToken,
        ]);

        $user = app(DiscordOAuthService::class)->handleCallback($discordUser);

        $this->assertSame('Mahasiswa PJJ', $user->name);
        $this->assertSame('discord-123', $user->discordAccount->discord_user_id);
        $this->assertSame($accessToken, $user->discordAccount->access_token_encrypted);
        $this->assertSame($refreshToken, $user->discordAccount->refresh_token_encrypted);
        $this->assertNotSame($accessToken, DB::table('discord_accounts')->value('access_token_encrypted'));
    }

    public function test_rejects_discord_user_who_is_not_a_guild_member(): void
    {
        config(['services.discord.guild_id' => 'guild-123']);
        Http::preventStrayRequests();
        Http::fake([
            'discord.com/api/v10/users/@me/guilds/guild-123/member' => Http::response([], 404),
        ]);

        $this->expectException(DiscordGuildMembershipException::class);

        app(DiscordOAuthService::class)->handleCallback(SocialiteUser::fake());
    }
}

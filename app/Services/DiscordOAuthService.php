<?php

namespace App\Services;

use App\Exceptions\DiscordGuildMembershipException;
use App\Models\DiscordAccount;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Throwable;

class DiscordOAuthService
{
    public function handleCallback(SocialiteUser $discordUser): User
    {
        $membership = $this->fetchGuildMembership($discordUser->token);
        $discordData = is_array($discordUser->user) ? $discordUser->user : [];
        $roles = array_values(Arr::get($membership, 'roles', []));

        return DB::transaction(function () use ($discordUser, $discordData, $roles): User {
            $discordAccount = DiscordAccount::query()
                ->where('discord_user_id', $discordUser->getId())
                ->first();

            $user = $discordAccount?->user ?? User::create([
                'name' => Arr::get($discordData, 'global_name') ?: $discordUser->getName(),
                'email' => $discordUser->getEmail(),
                'avatar_url' => $discordUser->getAvatar(),
                'role' => $this->resolveRole($roles),
                'status' => 'active',
                'is_active' => true,
                'last_login_at' => now(),
                'last_membership_checked_at' => now(),
            ]);

            $user->update([
                'name' => Arr::get($discordData, 'global_name') ?: $discordUser->getName(),
                'email' => $discordUser->getEmail(),
                'avatar_url' => $discordUser->getAvatar(),
                'role' => $this->resolveRole($roles),
                'status' => 'active',
                'is_active' => true,
                'last_login_at' => now(),
                'last_membership_checked_at' => now(),
            ]);

            $user->discordAccount()->updateOrCreate([], [
                'discord_user_id' => $discordUser->getId(),
                'username' => $discordUser->getNickname() ?: $discordUser->getName(),
                'global_name' => Arr::get($discordData, 'global_name'),
                'avatar_hash' => Arr::get($discordData, 'avatar'),
                'access_token_encrypted' => $discordUser->token,
                'refresh_token_encrypted' => $discordUser->refreshToken,
                'token_expires_at' => $discordUser->expiresIn ? now()->addSeconds($discordUser->expiresIn) : null,
                'roles_json' => $roles,
            ]);

            return $user->fresh('discordAccount');
        });
    }

    /** @return array<string, mixed> */
    public function fetchGuildMembership(string $accessToken): array
    {
        $guildId = config('services.discord.guild_id');

        if (! is_string($guildId) || $guildId === '') {
            throw DiscordGuildMembershipException::guildNotConfigured();
        }

        $response = Http::acceptJson()
            ->withToken($accessToken)
            ->timeout(10)
            ->get("https://discord.com/api/v10/users/@me/guilds/{$guildId}/member");

        if ($response->notFound() || $response->forbidden()) {
            throw DiscordGuildMembershipException::notMember();
        }

        if (! $response->successful()) {
            throw DiscordGuildMembershipException::verificationFailed();
        }

        return $response->json();
    }

    public function revalidateMembership(User $user): bool
    {
        $account = $user->discordAccount;

        if ($account === null || $account->access_token_encrypted === null) {
            return false;
        }

        try {
            $membership = $this->fetchGuildMembership($account->access_token_encrypted);
        } catch (DiscordGuildMembershipException $exception) {
            if ($exception->getMessage() === DiscordGuildMembershipException::notMember()->getMessage()) {
                $user->update(['status' => 'guild_revoked', 'is_active' => false]);
            }

            return false;
        } catch (Throwable) {
            return false;
        }

        $roles = array_values(Arr::get($membership, 'roles', []));
        $account->update(['roles_json' => $roles]);
        $user->update([
            'role' => $this->resolveRole($roles),
            'status' => 'active',
            'is_active' => true,
            'last_membership_checked_at' => now(),
        ]);

        return true;
    }

    /** @param array<int, string> $roles */
    private function resolveRole(array $roles): string
    {
        if (array_intersect($roles, config('services.discord.admin_role_ids', [])) !== []) {
            return 'admin';
        }

        if (array_intersect($roles, config('services.discord.reviewer_role_ids', [])) !== []) {
            return 'reviewer';
        }

        return 'student';
    }
}

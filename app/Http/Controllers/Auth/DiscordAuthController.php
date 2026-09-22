<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\DiscordGuildMembershipException;
use App\Http\Controllers\Controller;
use App\Services\DiscordOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class DiscordAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'guilds.members.read'])
            ->redirect();
    }

    public function callback(DiscordOAuthService $discordOAuthService): RedirectResponse
    {
        try {
            $discordUser = Socialite::driver('discord')->user();
            $user = $discordOAuthService->handleCallback($discordUser);
        } catch (DiscordGuildMembershipException $exception) {
            return redirect()->route('login')->withErrors(['oauth' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('login')->withErrors(['oauth' => 'Gagal terhubung dengan Discord.']);
        }

        if (! $user->is_active) {
            return redirect()->route('login')->withErrors(['oauth' => 'Akun Anda dinonaktifkan.']);
        }

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}

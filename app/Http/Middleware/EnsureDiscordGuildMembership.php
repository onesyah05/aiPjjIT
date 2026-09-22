<?php

namespace App\Http\Middleware;

use App\Services\DiscordOAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureDiscordGuildMembership
{
    public function __construct(private DiscordOAuthService $discordOAuthService) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->is_active || $user->status !== 'active') {
            Auth::logout();

            return redirect()->route('login')->withErrors(['oauth' => 'Akses akun Anda tidak aktif.']);
        }

        $recheckHours = max(1, (int) config('services.discord.membership_recheck_hours', 24));
        $membershipIsFresh = $user->last_membership_checked_at?->gte(now()->subHours($recheckHours)) ?? false;

        if (! $membershipIsFresh && ! $this->discordOAuthService->revalidateMembership($user)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'oauth' => 'Keanggotaan Discord perlu diverifikasi kembali. Silakan login ulang.',
            ]);
        }

        return $next($request);
    }
}

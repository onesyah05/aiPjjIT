<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $leaders = User::query()
            ->where('is_active', true)
            ->withCount(['knowledges as approved_knowledge_count' => fn ($query) => $query->where('status', 'approved')])
            ->withSum('aiCredentials as credential_successes', 'success_count')
            ->get(['id', 'name', 'avatar_url', 'role'])
            ->map(function (User $user): array {
                $knowledge = (int) $user->approved_knowledge_count;
                $successes = (int) ($user->credential_successes ?? 0);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'score' => ($knowledge * 25) + min($successes, 500),
                    'approved_knowledge_count' => $knowledge,
                    'credential_successes' => $successes,
                    'badges' => array_values(array_filter([
                        $knowledge >= 1 ? 'Kontributor' : null,
                        $knowledge >= 5 ? 'Knowledge Builder' : null,
                        $successes >= 25 ? 'AI Supporter' : null,
                        in_array($user->role, ['reviewer', 'admin'], true) ? 'Penjaga Mutu' : null,
                    ])),
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->take(50);

        return Inertia::render('Leaderboard', ['leaders' => $leaders]);
    }
}

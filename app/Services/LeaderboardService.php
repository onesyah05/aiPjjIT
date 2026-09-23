<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class LeaderboardService
{
    /**
     * Build the community leaderboard from approved knowledge and credential usage.
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     avatar_url: string|null,
     *     score: int,
     *     approved_knowledge_count: int,
     *     credential_successes: int,
     *     badges: array<int, string>,
     *     rank: int
     * }>
     */
    public function rankings(int $limit = 50): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->withCount(['knowledges as approved_knowledge_count' => fn ($query) => $query->where('status', 'approved')])
            ->withSum('aiCredentials as credential_successes', 'success_count')
            ->get(['id', 'name', 'avatar_url', 'role'])
            ->map(function (User $user): array {
                $knowledgeCount = (int) $user->approved_knowledge_count;
                $credentialSuccesses = (int) ($user->credential_successes ?? 0);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'score' => ($knowledgeCount * 25) + min($credentialSuccesses, 500),
                    'approved_knowledge_count' => $knowledgeCount,
                    'credential_successes' => $credentialSuccesses,
                    'badges' => array_values(array_filter([
                        $knowledgeCount >= 1 ? 'Kontributor' : null,
                        $knowledgeCount >= 5 ? 'Knowledge Builder' : null,
                        $credentialSuccesses >= 25 ? 'AI Supporter' : null,
                        in_array($user->role, ['reviewer', 'admin'], true) ? 'Penjaga Mutu' : null,
                    ])),
                ];
            })
            ->sortBy([
                ['score', 'desc'],
                ['name', 'asc'],
            ])
            ->values()
            ->map(fn (array $leader, int $index): array => [...$leader, 'rank' => $index + 1])
            ->take($limit);
    }
}

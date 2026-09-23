<?php

namespace App\Services;

use App\Models\AiCredential;
use App\Notifications\CredentialNeedsAttention;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CredentialPoolService
{
    /**
     * Get the best available API credential from the pool.
     * Selects an active credential that hasn't hit the rate limit recently,
     * ordered by the lowest quota used.
     *
     * @return AiCredential|null
     */
    /** @return Collection<int, AiCredential> */
    public function getAvailableCredentials(int $limit = 3): Collection
    {
        return AiCredential::query()
            ->where('status', 'active')
            ->where('community_enabled', true)
            ->where(function ($query): void {
                $query->whereNull('cooldown_until')->orWhere('cooldown_until', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('daily_request_limit')
                    ->orWhereRaw('daily_request_limit > COALESCE((SELECT request_count FROM ai_credential_usage_daily WHERE credential_id = ai_credentials.id AND date = ?), 0)', [today()->toDateString()]);
            })
            ->orderByRaw('(success_count - failure_count) DESC')
            ->orderBy('last_used_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark a credential as having hit the rate limit.
     */
    public function markRateLimitHit(AiCredential $credential): void
    {
        $credential->update([
            'cooldown_until' => now()->addMinutes(5),
            'last_error_code' => 'rate_limited',
        ]);

        Log::warning('AI credential entered cooldown.', ['credential_id' => $credential->id]);
    }

    /**
     * Increment the quota used for a credential.
     *
     * @param  int  $amount
     */
    public function recordAttempt(AiCredential $credential): void
    {
        DB::transaction(function () use ($credential): void {
            $credential->update(['last_used_at' => now()]);
            $this->incrementDailyUsage($credential, 'request_count');
        });
    }

    public function recordSuccess(AiCredential $credential): void
    {
        $credential->increment('success_count');
        $credential->update(['last_error_code' => null]);
        $this->incrementDailyUsage($credential, 'success_count');
    }

    public function recordFailure(AiCredential $credential, string $errorCode): void
    {
        $credential->increment('failure_count');
        $credential->update(['last_error_code' => $errorCode]);
        $this->incrementDailyUsage($credential, 'failure_count');
    }

    public function markInvalid(AiCredential $credential, string $errorCode, string $reason): void
    {
        $shouldNotify = $credential->status !== 'invalid';

        $credential->update([
            'status' => 'invalid',
            'last_error_code' => $errorCode,
        ]);

        if ($shouldNotify) {
            $credential->user?->notify(new CredentialNeedsAttention(
                credentialId: $credential->id,
                credentialLabel: $credential->label,
                status: 'invalid',
                reason: $reason,
            ));
        }
    }

    private function incrementDailyUsage(AiCredential $credential, string $column): void
    {
        DB::table('ai_credential_usage_daily')->updateOrInsert(
            ['credential_id' => $credential->id, 'date' => today()->toDateString()],
            ['created_at' => now(), 'updated_at' => now()],
        );

        DB::table('ai_credential_usage_daily')
            ->where('credential_id', $credential->id)
            ->where('date', today()->toDateString())
            ->increment($column);
    }
}

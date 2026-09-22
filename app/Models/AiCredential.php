<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'provider', 'credential_type', 'encrypted_secret', 'fingerprint', 'label', 'masked_preview', 'status', 'community_enabled', 'daily_request_limit', 'cooldown_until', 'failure_count', 'success_count', 'last_used_at', 'last_error_code', 'validated_at'])]
#[Hidden(['encrypted_secret', 'fingerprint'])]
class AiCredential extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encrypted_secret' => 'encrypted',
            'community_enabled' => 'boolean',
            'cooldown_until' => 'datetime',
            'last_used_at' => 'datetime',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * Get the user that donated the credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

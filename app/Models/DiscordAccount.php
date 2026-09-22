<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'discord_user_id', 'username', 'global_name', 'avatar_hash', 'access_token_encrypted', 'refresh_token_encrypted', 'token_expires_at', 'roles_json'])]
#[Hidden(['access_token_encrypted', 'refresh_token_encrypted'])]
class DiscordAccount extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'access_token_encrypted' => 'encrypted',
            'refresh_token_encrypted' => 'encrypted',
            'roles_json' => 'array',
        ];
    }

    /**
     * Get the user that owns the discord account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

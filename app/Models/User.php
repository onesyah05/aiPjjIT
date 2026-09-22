<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'avatar_url', 'role', 'status', 'is_active', 'last_login_at', 'last_membership_checked_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'last_membership_checked_at' => 'datetime',
        ];
    }

    /**
     * Get the user's Discord account.
     */
    public function discordAccount(): HasOne
    {
        return $this->hasOne(DiscordAccount::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function knowledges(): HasMany
    {
        return $this->hasMany(Knowledge::class);
    }

    public function aiCredentials(): HasMany
    {
        return $this->hasMany(AiCredential::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(UserFeedback::class);
    }
}

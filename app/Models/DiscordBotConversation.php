<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'discord_channel_id',
    'discord_user_id',
    'discord_message_id',
    'conversation_id',
    'discord_username',
    'question',
    'answer',
    'status',
    'error_code',
    'latency_ms',
])]
class DiscordBotConversation extends Model
{
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    protected function casts(): array
    {
        return [
            'latency_ms' => 'integer',
        ];
    }
}

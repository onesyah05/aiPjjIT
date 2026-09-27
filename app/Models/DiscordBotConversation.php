<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'discord_channel_id',
    'discord_user_id',
    'discord_message_id',
    'discord_username',
    'question',
    'answer',
    'status',
    'error_code',
    'latency_ms',
])]
class DiscordBotConversation extends Model
{
    protected function casts(): array
    {
        return [
            'latency_ms' => 'integer',
        ];
    }
}

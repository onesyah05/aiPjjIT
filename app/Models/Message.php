<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['conversation_id', 'client_request_id', 'role', 'content', 'status', 'provider', 'model', 'input_tokens', 'output_tokens', 'latency_ms', 'error_code', 'metadata_json'])]
class Message extends Model
{
    protected function casts(): array
    {
        return ['metadata_json' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(MessageSource::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(UserFeedback::class);
    }
}

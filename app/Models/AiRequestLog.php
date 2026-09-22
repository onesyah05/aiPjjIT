<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'conversation_id', 'message_id', 'credential_id', 'operation', 'provider', 'model', 'status', 'error_code', 'latency_ms', 'input_tokens', 'output_tokens', 'retrieved_chunks'])]
class AiRequestLog extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(AiCredential::class, 'credential_id');
    }
}

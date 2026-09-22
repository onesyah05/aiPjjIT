<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['message_id', 'knowledge_chunk_id', 'knowledge_version_id', 'rank', 'similarity_score'])]
class MessageSource extends Model
{
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'knowledge_chunk_id');
    }
}

<?php

namespace App\Models;

use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['knowledge_version_id', 'chunk_index', 'heading_path', 'content', 'token_count', 'vector_external_id'])]
class KnowledgeChunk extends Model
{
    /** @use HasFactory<KnowledgeChunkFactory> */
    use HasFactory;

    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeVersion::class, 'knowledge_version_id');
    }
}

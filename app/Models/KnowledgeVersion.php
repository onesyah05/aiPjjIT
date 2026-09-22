<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['knowledge_id', 'version', 'content', 'source_type', 'original_filename', 'status', 'is_embedded', 'processing_status', 'processing_error', 'embedded_at', 'submitted_at', 'reviewed_at', 'reviewed_by', 'review_note'])]
class KnowledgeVersion extends Model
{
    protected function casts(): array
    {
        return [
            'is_embedded' => 'boolean',
            'embedded_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function knowledge(): BelongsTo
    {
        return $this->belongsTo(Knowledge::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(KnowledgeReview::class);
    }
}

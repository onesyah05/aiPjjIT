<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['knowledge_version_id', 'reviewer_id', 'action', 'note'])]
class KnowledgeReview extends Model
{
    public function version(): BelongsTo
    {
        return $this->belongsTo(KnowledgeVersion::class, 'knowledge_version_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['user_id', 'course_id', 'title', 'description', 'visibility', 'status', 'active_version_id'])]
class Knowledge extends Model
{
    use SoftDeletes;

    protected $table = 'knowledges';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeVersion::class);
    }

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(KnowledgeVersion::class, 'active_version_id');
    }
}

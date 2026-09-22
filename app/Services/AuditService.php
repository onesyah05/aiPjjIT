<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditService
{
    /** @param array<string, mixed> $metadata */
    public function record(?User $actor, string $action, Model|string $resource, ?int $resourceId = null, array $metadata = [], ?Request $request = null): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor?->id,
            'action' => $action,
            'resource_type' => $resource instanceof Model ? $resource->getMorphClass() : $resource,
            'resource_id' => $resource instanceof Model ? $resource->getKey() : $resourceId,
            'metadata_json' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
            'ip_hash' => $request ? hash('sha256', (string) $request->ip().config('app.key')) : null,
            'created_at' => now(),
        ]);
    }
}

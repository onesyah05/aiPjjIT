<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewKnowledgeRequest;
use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\KnowledgeVersion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeReviewController extends Controller
{
    public function index(): Response
    {
        abort_unless(in_array(request()->user()->role, ['reviewer', 'admin'], true), 403);

        return Inertia::render('Admin/KnowledgeReviews', [
            'versions' => KnowledgeVersion::query()
                ->with(['knowledge.user:id,name', 'knowledge.course:id,code,name'])
                ->where('status', 'pending_review')
                ->oldest('submitted_at')
                ->paginate(20),
        ]);
    }

    public function update(ReviewKnowledgeRequest $request, KnowledgeVersion $version): RedirectResponse
    {
        abort_unless($version->status === 'pending_review', 422);

        $validated = $request->validated();
        $status = match ($validated['action']) {
            'approve' => 'approved',
            'reject' => 'rejected',
            'request_revision' => 'draft',
        };

        DB::transaction(function () use ($request, $version, $validated, $status): void {
            $version->update([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $request->user()->id,
                'review_note' => $validated['note'] ?? null,
            ]);

            $version->reviews()->create([
                'reviewer_id' => $request->user()->id,
                'action' => $validated['action'],
                'note' => $validated['note'] ?? null,
            ]);

            $hasApprovedActiveVersion = $version->knowledge->activeVersion?->status === 'approved';
            $version->knowledge->update([
                'status' => $status === 'approved' || $hasApprovedActiveVersion ? 'approved' : $status,
                'active_version_id' => $status === 'approved' ? $version->id : $version->knowledge->active_version_id,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $request->user()->id,
                'action' => 'knowledge.'.$validated['action'],
                'resource_type' => 'knowledge_version',
                'resource_id' => $version->id,
                'metadata_json' => json_encode(['knowledge_id' => $version->knowledge_id]),
                'ip_hash' => hash('sha256', (string) $request->ip().config('app.key')),
                'created_at' => now(),
            ]);
        });

        if ($status === 'approved') {
            ProcessKnowledgeEmbedding::dispatch($version);
        }

        return back()->with('status', 'Review knowledge berhasil disimpan.');
    }
}

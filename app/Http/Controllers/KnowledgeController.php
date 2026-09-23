<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreKnowledgeRequest;
use App\Http\Requests\UpdateKnowledgeRequest;
use App\Jobs\DeleteKnowledgeVectors;
use App\Jobs\ProcessKnowledgeEmbedding;
use App\Models\Course;
use App\Models\Knowledge;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $context = $request->string('context')->toString();
        $search = $request->string('q')->trim()->toString();

        if (! in_array($context, ['general', 'course', 'mine'], true)) {
            $context = 'all';
        }

        $accessibleKnowledge = Knowledge::query()
            ->where(function ($query) use ($user): void {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($shared): void {
                        $shared->where('status', 'approved')->whereIn('visibility', ['course', 'community']);
                    });
            });

        $knowledge = (clone $accessibleKnowledge)
            ->with(['course:id,code,name', 'user:id,name', 'activeVersion:id,knowledge_id,version,status'])
            ->when($context === 'general', fn ($query) => $query->whereNull('course_id'))
            ->when($context === 'course', fn ($query) => $query->whereNotNull('course_id'))
            ->when($context === 'mine', fn ($query) => $query->where('user_id', $user->id))
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Knowledge/Index', [
            'knowledge' => $knowledge,
            'courses' => Course::query()->where('status', 'active')->orderBy('name')->get(),
            'filters' => ['context' => $context, 'q' => $search],
            'counts' => [
                'all' => (clone $accessibleKnowledge)->count(),
                'general' => (clone $accessibleKnowledge)->whereNull('course_id')->count(),
                'course' => (clone $accessibleKnowledge)->whereNotNull('course_id')->count(),
                'mine' => (clone $accessibleKnowledge)->where('user_id', $user->id)->count(),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreKnowledgeRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $file = $request->file('file');
        $content = $file ? $file->get() : (string) $validated['content'];
        $content = str_replace("\0", '', $content);
        $visibility = $validated['visibility'];
        $status = $visibility === 'private' ? 'approved' : 'pending_review';

        $knowledge = $request->user()->knowledges()->create([
            'course_id' => $validated['context'] === 'course' ? $validated['course_id'] : null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'visibility' => $visibility,
            'status' => $status,
        ]);

        $version = $knowledge->versions()->create([
            'version' => 1,
            'content' => $content,
            'source_type' => $file ? 'md_upload' : 'manual',
            'original_filename' => $file?->getClientOriginalName(),
            'status' => $status,
            'submitted_at' => $status === 'pending_review' ? now() : null,
            'reviewed_at' => $status === 'approved' ? now() : null,
        ]);

        $knowledge->update(['active_version_id' => $version->id]);
        ProcessKnowledgeEmbedding::dispatch($version);
        $this->audit->record($request->user(), 'knowledge.created', $knowledge, metadata: ['visibility' => $visibility], request: $request);

        return redirect()->route('knowledge.index')->with('status', 'Knowledge berhasil disimpan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Knowledge $knowledge): Response
    {
        $this->authorize('view', $knowledge);

        return Inertia::render('Knowledge/Show', [
            'knowledge' => $knowledge->load(['course:id,code,name', 'user:id,name', 'versions' => fn ($query) => $query->latest('version')]),
            'courses' => Course::query()->where('status', 'active')->orderBy('name')->get(['id', 'code', 'name']),
            'canEdit' => $request->user()->can('update', $knowledge),
            'canDelete' => $request->user()->can('delete', $knowledge),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateKnowledgeRequest $request, Knowledge $knowledge): RedirectResponse
    {
        $this->authorize('update', $knowledge);

        $validated = $request->validated();
        $file = $request->file('file');
        $content = $file ? $file->get() : ($validated['content'] ?? null);

        DB::transaction(function () use ($knowledge, $validated, $file, $content): void {
            $visibility = $validated['visibility'];
            $knowledge->update([
                'course_id' => $validated['context'] === 'course' ? $validated['course_id'] : null,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'visibility' => $visibility,
            ]);

            if ($content === null || trim((string) $content) === '') {
                return;
            }

            $content = str_replace("\0", '', (string) $content);
            $activeContent = $knowledge->activeVersion?->content;

            if ($content === $activeContent) {
                return;
            }

            $status = $visibility === 'private' ? 'approved' : 'pending_review';
            $version = $knowledge->versions()->create([
                'version' => ((int) $knowledge->versions()->max('version')) + 1,
                'content' => $content,
                'source_type' => $file ? 'md_upload' : 'manual',
                'original_filename' => $file?->getClientOriginalName(),
                'status' => $status,
                'submitted_at' => $status === 'pending_review' ? now() : null,
                'reviewed_at' => $status === 'approved' ? now() : null,
            ]);

            if ($status === 'approved') {
                $knowledge->update(['active_version_id' => $version->id, 'status' => 'approved']);
                ProcessKnowledgeEmbedding::dispatch($version);
            } else {
                $knowledge->update([
                    'status' => $knowledge->activeVersion?->status === 'approved' ? 'approved' : 'pending_review',
                ]);
            }
        });
        $this->audit->record($request->user(), 'knowledge.updated', $knowledge, metadata: ['visibility' => $validated['visibility']], request: $request);

        return back()->with('status', 'Knowledge dan riwayat versinya berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Knowledge $knowledge): RedirectResponse
    {
        $this->authorize('delete', $knowledge);
        $knowledgeId = $knowledge->id;
        $this->audit->record($request->user(), 'knowledge.deleted', $knowledge, request: $request);
        $knowledge->delete();
        DeleteKnowledgeVectors::dispatch($knowledgeId);

        return redirect()->route('knowledge.index');
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConversationRequest;
use App\Models\Conversation;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $search = $request->string('q')->trim()->toString();

        return Inertia::render('Conversations/Index', [
            'conversations' => $request->user()->conversations()
                ->with('course:id,code,name')
                ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
                ->latest('last_message_at')
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['q' => $search],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreConversationRequest $request): RedirectResponse
    {
        $conversation = $request->user()->conversations()->create($request->validated());

        return redirect()->route('conversations.show', $conversation);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        $conversation->load([
            'course:id,code,name',
            'messages' => fn ($query) => $query->with('sources.chunk.version.knowledge:id,title')->orderBy('id'),
        ]);

        return Inertia::render('Conversations/Show', [
            'conversation' => $conversation,
            'courses' => Course::query()->where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $this->authorize('update', $conversation);

        $validated = $request->validate([
            'title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'course_id' => ['sometimes', 'nullable', 'integer', 'exists:courses,id'],
            'mode' => ['sometimes', 'in:general,knowledge_only'],
        ]);

        $conversation->update($validated);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Conversation $conversation): RedirectResponse
    {
        $this->authorize('delete', $conversation);
        $conversation->delete();

        return redirect()->route('conversations.index');
    }
}

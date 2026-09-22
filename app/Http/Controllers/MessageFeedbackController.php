<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\UserFeedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageFeedbackController extends Controller
{
    public function store(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->conversation()->where('user_id', $request->user()->id)->exists(), 403);
        abort_unless($message->role === 'assistant', 422);
        $validated = $request->validate([
            'rating' => ['required', 'in:helpful,not_helpful'],
            'reason' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        UserFeedback::query()->updateOrCreate(
            ['user_id' => $request->user()->id, 'message_id' => $message->id],
            $validated,
        );

        return back()->with('status', 'Terima kasih, umpan balik Anda tersimpan.');
    }
}

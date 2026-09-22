<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'courses' => Course::query()->where('status', 'active')->orderBy('semester')->orderBy('name')->get(),
            'recentConversations' => $user->conversations()
                ->with('course:id,code,name')
                ->latest('last_message_at')
                ->limit(6)
                ->get(),
            'recentKnowledge' => $user->knowledges()
                ->with('course:id,code,name')
                ->latest()
                ->limit(5)
                ->get(),
            'stats' => [
                'knowledge_count' => $user->knowledges()->count(),
                'active_credentials' => $user->aiCredentials()->where('status', 'active')->count(),
                'conversation_count' => $user->conversations()->count(),
            ],
        ]);
    }
}

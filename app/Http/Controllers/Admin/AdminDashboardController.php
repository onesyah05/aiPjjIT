<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCredential;
use App\Models\AiRequestLog;
use App\Models\Knowledge;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'active_users' => User::query()->where('is_active', true)->count(),
                'knowledge' => Knowledge::query()->count(),
                'pending_knowledge' => Knowledge::query()->where('status', 'pending_review')->count(),
                'requests_today' => AiRequestLog::query()->whereDate('created_at', today())->count(),
                'failed_today' => AiRequestLog::query()->whereDate('created_at', today())->where('status', 'failed')->count(),
            ],
            'credentialHealth' => AiCredential::query()->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status'),
            'recentRequests' => AiRequestLog::query()->with('user:id,name')->latest()->limit(10)->get(),
            'dailyRequests' => AiRequestLog::query()
                ->selectRaw('DATE(created_at) as date, count(*) as total, sum(case when status = ? then 1 else 0 end) as failed', ['failed'])
                ->where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->groupByRaw('DATE(created_at)')
                ->orderBy('date')
                ->get(),
        ]);
    }
}

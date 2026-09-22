<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        return Inertia::render('Admin/AuditLogs', [
            'logs' => DB::table('audit_logs')
                ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_user_id')
                ->select('audit_logs.*', 'users.name as actor_name')
                ->latest('audit_logs.created_at')
                ->paginate(30),
        ]);
    }
}

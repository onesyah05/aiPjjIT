<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCredential;
use App\Services\AI\GeminiService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CredentialController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);

        return Inertia::render('Admin/Credentials', [
            'credentials' => AiCredential::query()->with('user:id,name,email')->latest()->paginate(25),
        ]);
    }

    public function update(Request $request, AiCredential $credential): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $credential->update($request->validate(['status' => ['required', 'in:active,disabled,invalid,cooldown']]));
        $this->audit->record($request->user(), 'credential.status_updated', $credential, metadata: ['status' => $credential->status], request: $request);

        return back()->with('status', 'Status credential diperbarui.');
    }

    public function validateCredential(Request $request, AiCredential $credential, GeminiService $gemini): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $healthy = $gemini->validateCredential($credential->encrypted_secret);
        $credential->update([
            'status' => $healthy ? 'active' : 'invalid',
            'validated_at' => now(),
            'last_error_code' => $healthy ? null : 'validation_failed',
        ]);
        $this->audit->record($request->user(), 'credential.health_checked', $credential, metadata: ['healthy' => $healthy], request: $request);

        return back()->with('status', $healthy ? 'Credential sehat dan dapat digunakan.' : 'Credential tidak valid atau tidak dapat dijangkau.');
    }
}

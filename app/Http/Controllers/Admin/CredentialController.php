<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCredential;
use App\Notifications\CredentialNeedsAttention;
use App\Services\AI\GeminiService;
use App\Services\AuditService;
use App\Services\CredentialPoolService;
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
        $previousStatus = $credential->status;
        $credential->update($request->validate(['status' => ['required', 'in:active,disabled,invalid,cooldown']]));

        if ($previousStatus !== $credential->status && in_array($credential->status, ['disabled', 'invalid'], true)) {
            $credential->user?->notify(new CredentialNeedsAttention(
                credentialId: $credential->id,
                credentialLabel: $credential->label,
                status: $credential->status,
                reason: $credential->status === 'disabled'
                    ? 'Credential dinonaktifkan oleh administrator dan tidak akan digunakan untuk request baru.'
                    : 'Credential ditandai tidak valid oleh administrator. Periksa credential sebelum mengaktifkannya kembali.',
            ));
        }

        $this->audit->record($request->user(), 'credential.status_updated', $credential, metadata: ['status' => $credential->status], request: $request);

        return back()->with('status', 'Status credential diperbarui.');
    }

    public function validateCredential(
        Request $request,
        AiCredential $credential,
        GeminiService $gemini,
        CredentialPoolService $credentialPool,
    ): RedirectResponse {
        abort_unless($request->user()->role === 'admin', 403);
        $healthy = $gemini->validateCredential($credential->encrypted_secret);
        if ($healthy) {
            $credential->update([
                'status' => 'active',
                'validated_at' => now(),
                'last_error_code' => null,
            ]);
        } else {
            $credentialPool->markInvalid(
                $credential,
                'validation_failed',
                'Pemeriksaan kesehatan gagal. Credential tidak dapat digunakan dan memerlukan perhatian Anda.',
            );
            $credential->update(['validated_at' => now()]);
        }
        $this->audit->record($request->user(), 'credential.health_checked', $credential, metadata: ['healthy' => $healthy], request: $request);

        return back()->with('status', $healthy ? 'Credential sehat dan dapat digunakan.' : 'Credential tidak valid atau tidak dapat dijangkau.');
    }
}

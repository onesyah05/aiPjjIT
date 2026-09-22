<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAiCredentialRequest;
use App\Models\AiCredential;
use App\Services\AI\GeminiService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AiCredentialController extends Controller
{
    public function __construct(private AuditService $audit) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Contributions/Index', [
            'credentials' => $request->user()->aiCredentials()
                ->latest()
                ->get(['id', 'label', 'provider', 'masked_preview', 'status', 'community_enabled', 'daily_request_limit', 'success_count', 'failure_count', 'last_used_at', 'validated_at']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAiCredentialRequest $request, GeminiService $gemini): RedirectResponse
    {
        $validated = $request->validated();
        $secret = $validated['secret'];

        if (! $gemini->validateCredential($secret)) {
            throw ValidationException::withMessages(['secret' => 'Credential Gemini tidak valid atau tidak dapat digunakan.']);
        }

        $fingerprint = hash_hmac('sha256', $secret, (string) config('app.key'));

        if (AiCredential::withTrashed()->where('fingerprint', $fingerprint)->exists()) {
            throw ValidationException::withMessages(['secret' => 'Credential ini sudah pernah didaftarkan.']);
        }

        $credential = $request->user()->aiCredentials()->create([
            'provider' => 'gemini',
            'credential_type' => 'api_key',
            'encrypted_secret' => $secret,
            'fingerprint' => $fingerprint,
            'label' => $validated['label'],
            'masked_preview' => '••••'.substr($secret, -4),
            'status' => 'active',
            'community_enabled' => $validated['community_enabled'],
            'daily_request_limit' => $validated['daily_request_limit'] ?? null,
            'validated_at' => now(),
        ]);
        $this->audit->record($request->user(), 'credential.created', $credential, request: $request);

        return back()->with('status', 'Credential berhasil divalidasi dan disimpan secara terenkripsi.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, AiCredential $aiCredential): RedirectResponse
    {
        $this->authorize('update', $aiCredential);

        $validated = $request->validate([
            'community_enabled' => ['sometimes', 'boolean'],
            'daily_request_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
            'status' => ['sometimes', 'in:active,disabled'],
        ]);

        $aiCredential->update($validated);
        $this->audit->record($request->user(), 'credential.updated', $aiCredential, metadata: ['status' => $aiCredential->status], request: $request);

        return back();
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, AiCredential $aiCredential): RedirectResponse
    {
        $this->authorize('delete', $aiCredential);
        $this->audit->record($request->user(), 'credential.deleted', $aiCredential, request: $request);
        $aiCredential->delete();

        return back()->with('status', 'Credential telah dihapus.');
    }
}

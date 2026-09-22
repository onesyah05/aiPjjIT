<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->role === 'admin', 403);
        $search = $request->string('q')->trim()->toString();

        return Inertia::render('Admin/Users', [
            'users' => User::query()
                ->with('discordAccount:id,user_id,discord_user_id,username,roles_json')
                ->withCount(['knowledges', 'conversations'])
                ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                }))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'filters' => ['q' => $search],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        abort_if($request->user()->is($user) && $request->validated('role') !== 'admin', 422, 'Anda tidak dapat mencabut akses admin sendiri.');
        $user->update($request->validated());
        $this->audit->record($request->user(), 'user.access_updated', $user, metadata: $request->validated(), request: $request);

        return back()->with('status', 'Akses pengguna berhasil diperbarui.');
    }
}

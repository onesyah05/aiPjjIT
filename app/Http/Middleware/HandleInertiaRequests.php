<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'notifications' => fn (): array => $this->notificationsFor($request),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }

    /** @return array{unread_count: int, items: array<int, array<string, mixed>>} */
    private function notificationsFor(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return ['unread_count' => 0, 'items' => []];
        }

        return [
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'data' => $notification->data,
                    'read_at' => $notification->read_at?->toIso8601String(),
                    'created_at' => $notification->created_at?->toIso8601String(),
                ])
                ->all(),
        ];
    }
}

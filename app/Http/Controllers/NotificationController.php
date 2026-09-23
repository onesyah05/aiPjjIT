<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function read(Request $request, string $notification): RedirectResponse
    {
        $databaseNotification = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        $databaseNotification->markAsRead();

        return redirect()->to((string) data_get($databaseNotification->data, 'url', route('dashboard')));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'Semua notifikasi telah ditandai dibaca.');
    }
}

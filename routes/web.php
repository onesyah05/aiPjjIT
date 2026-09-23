<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CredentialController;
use App\Http\Controllers\Admin\KnowledgeReviewController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiCredentialController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KnowledgeController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\MessageFeedbackController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/privacy', fn () => Inertia::render('Privacy'))->name('privacy');

Route::middleware(['auth', 'discord.member'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('conversations', ConversationController::class)->except(['create', 'edit']);
    Route::post('/conversations/{conversation}/messages', [ChatController::class, 'stream'])
        ->middleware('throttle:chat')
        ->name('conversations.messages.store');

    Route::resource('knowledge', KnowledgeController::class)->except(['create', 'edit']);
    Route::get('/leaderboard', LeaderboardController::class)->name('leaderboard');
    Route::post('/messages/{message}/feedback', [MessageFeedbackController::class, 'store'])->name('messages.feedback.store');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::resource('ai-credentials', AiCredentialController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('/admin/knowledge-reviews', [KnowledgeReviewController::class, 'index'])->name('admin.knowledge-reviews.index');
    Route::patch('/admin/knowledge-reviews/{version}', [KnowledgeReviewController::class, 'update'])->name('admin.knowledge-reviews.update');
    Route::resource('/admin/courses', CourseController::class)->only(['index', 'store', 'update', 'destroy'])->names('admin.courses');
    Route::get('/admin', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::patch('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::get('/admin/credentials', [CredentialController::class, 'index'])->name('admin.credentials.index');
    Route::patch('/admin/credentials/{credential}', [CredentialController::class, 'update'])->name('admin.credentials.update');
    Route::post('/admin/credentials/{credential}/validate', [CredentialController::class, 'validateCredential'])->name('admin.credentials.validate');
    Route::get('/admin/audit-logs', AuditLogController::class)->name('admin.audit-logs.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

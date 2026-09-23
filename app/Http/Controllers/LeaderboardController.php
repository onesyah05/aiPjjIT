<?php

namespace App\Http\Controllers;

use App\Services\LeaderboardService;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(LeaderboardService $leaderboard): Response
    {
        return Inertia::render('Leaderboard', [
            'leaders' => $leaderboard->rankings(),
        ]);
    }
}

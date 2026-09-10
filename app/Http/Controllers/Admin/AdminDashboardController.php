<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totals = [
            'totalUsers' => (int) User::query()->count(),
            'totalCompetitions' => (int) PhotoCompetition::query()->count(),
            'totalPhotos' => (int) Photo::query()->count(),
            'totalLikes' => (int) Photo::query()->sum('likes_count'),
        ];

        $days = collect(range(13, 0))
            ->map(fn(int $offset) => now()->subDays($offset)->toDateString());

        $dailyRows = Photo::query()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as date, count(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        $dailyVolume = $days->map(fn(string $date) => [
            'date' => $date,
            'submitted' => (int) ($dailyRows[$date] ?? 0),
        ])->values();

        return response()->json([
            'data' => [
                'totals' => $totals,
                'dailyVolume' => $dailyVolume,
            ],
        ]);
    }
}

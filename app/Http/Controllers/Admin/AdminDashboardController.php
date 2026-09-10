<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminDashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $totals = [
            'totalConversations' => (int) ChatConversation::query()->count(),
            'totalMessages' => (int) ChatMessage::query()->count(),
            'totalUsers' => (int) User::query()->count(),
        ];

        $days = collect(range(13, 0))
            ->map(fn (int $offset) => now()->subDays($offset)->toDateString());

        $dailyRows = ChatMessage::query()
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('DATE(created_at) as date, count(*) as aggregate')
            ->groupBy('date')
            ->pluck('aggregate', 'date');

        $dailyVolume = $days->map(fn (string $date) => [
            'date' => $date,
            'sent' => (int) ($dailyRows[$date] ?? 0),
        ])->values();

        return response()->json([
            'data' => [
                'totals' => $totals,
                'dailyVolume' => $dailyVolume,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPhotoCompetitionController extends Controller
{
    /**
     * Stats overview plus the recent competitions and the configurable prize amount.
     */
    public function index(): JsonResponse
    {
        $current = PhotoCompetition::active()->withCount('photos')->first();

        $totals = [
            'totalCompetitions' => PhotoCompetition::query()->count(),
            'totalPhotos' => Photo::query()->count(),
            'totalLikes' => (int) Photo::query()->sum('likes_count'),
        ];

        $recentCompetitions = PhotoCompetition::query()
            ->withCount('photos')
            ->with('winnerPhoto.user')
            ->latest('starts_at')
            ->limit(10)
            ->get()
            ->map(fn(PhotoCompetition $competition) => [
                'id' => $competition->id,
                'startsAt' => $competition->starts_at,
                'endsAt' => $competition->ends_at,
                'status' => $competition->status,
                'prizeAmount' => $competition->prize_amount,
                'photosCount' => $competition->photos_count,
                'winnerName' => $competition->winnerPhoto?->user?->name,
            ]);

        return response()->json([
            'data' => [
                'current' => $current ? [
                    'id' => $current->id,
                    'endsAt' => $current->ends_at,
                    'prizeAmount' => $current->prize_amount,
                    'photosCount' => $current->photos_count,
                ] : null,
                'totals' => $totals,
                'prizeAmount' => (int) (Setting::query()->where('key', 'photo_prize_amount')->value('value') ?? 500),
                'recentCompetitions' => $recentCompetitions,
            ],
        ]);
    }

    /**
     * Set the default weekly prize amount used for future competitions.
     */
    public function updatePrizeAmount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prizeAmount' => 'required|integer|min:0',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'photo_prize_amount'],
            ['value' => $data['prizeAmount']]
        );

        return response()->json(['data' => ['prizeAmount' => $data['prizeAmount']]]);
    }
}

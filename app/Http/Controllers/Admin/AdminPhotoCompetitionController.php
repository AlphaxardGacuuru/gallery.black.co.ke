<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminPhotoCompetitionController extends Controller
{
    /**
     * Stats overview, the active competition, and the configurable prize
     * amount/schedule. The full competition history is paginated separately
     * via recent() so paging through it doesn't refetch all of this too.
     */
    public function index(): JsonResponse
    {
        $current = PhotoCompetition::active()->withCount('photos')->first();

        $totals = [
            'totalCompetitions' => PhotoCompetition::query()->count(),
            'totalPhotos' => Photo::query()->count(),
            'totalLikes' => (int) Photo::query()->sum('likes_count'),
        ];

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
                'schedule' => PhotoCompetition::schedule(),
            ],
        ]);
    }

    /**
     * Paginated competition history, most recent first.
     */
    public function recent(Request $request): JsonResponse
    {
        $competitions = PhotoCompetition::query()
            ->withCount('photos')
            ->with('winnerPhoto.user')
            ->latest('starts_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $competitions->getCollection()->map(fn(PhotoCompetition $competition) => [
                'id' => $competition->id,
                'startsAt' => $competition->starts_at,
                'endsAt' => $competition->ends_at,
                'status' => $competition->status,
                'prizeAmount' => $competition->prize_amount,
                'photosCount' => $competition->photos_count,
                'winnerName' => $competition->winnerPhoto?->user?->name,
            ]),
            'meta' => [
                'current_page' => $competitions->currentPage(),
                'last_page' => $competitions->lastPage(),
                'total' => $competitions->total(),
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

    /**
     * Set the weekly start/end day and time used by the scheduler to open
     * and close future competitions.
     */
    public function updateSchedule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'startDay' => 'required|integer|min:0|max:6',
            'startTime' => 'required|date_format:H:i',
            'endDay' => 'required|integer|min:0|max:6',
            'endTime' => 'required|date_format:H:i',
        ]);

        $startMinuteOfWeek = $data['startDay'] * 1440 + $this->minutesFromTime($data['startTime']);
        $endMinuteOfWeek = $data['endDay'] * 1440 + $this->minutesFromTime($data['endTime']);

        if ($endMinuteOfWeek <= $startMinuteOfWeek) {
            throw ValidationException::withMessages([
                'endDay' => 'The competition must end after it starts.',
            ]);
        }

        foreach ([
            'photo_competition_start_day' => $data['startDay'],
            'photo_competition_start_time' => $data['startTime'],
            'photo_competition_end_day' => $data['endDay'],
            'photo_competition_end_time' => $data['endTime'],
        ] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json(['data' => PhotoCompetition::schedule()]);
    }

    /**
     * Directly edit the currently active competition's prize amount and end
     * time — for correcting mistakes or extending/shortening this week's
     * challenge without waiting for the scheduler.
     */
    public function updateActive(Request $request): JsonResponse
    {
        $competition = PhotoCompetition::active()->withCount('photos')->first();

        if (! $competition) {
            throw ValidationException::withMessages([
                'prizeAmount' => 'There is no active competition to edit.',
            ]);
        }

        $data = $request->validate([
            'prizeAmount' => 'required|integer|min:0',
            'endsAt' => 'required|date|after:' . $competition->starts_at,
        ]);

        $competition->update([
            'prize_amount' => $data['prizeAmount'],
            'ends_at' => $data['endsAt'],
        ]);

        return response()->json([
            'data' => [
                'id' => $competition->id,
                'endsAt' => $competition->ends_at,
                'prizeAmount' => $competition->prize_amount,
                'photosCount' => $competition->photos_count,
            ],
        ]);
    }

    private function minutesFromTime(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}

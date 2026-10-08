<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\KopokopoTransferService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\PhotoSlotPurchase;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminPhotoCompetitionController extends Controller
{
    public function __construct(protected KopokopoTransferService $kopokopoTransferService)
    {
        //
    }

    /**
     * Stats overview, the active competition, and the configurable prize
     * tiers/schedule. The full competition history is paginated separately
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
                    'photosCount' => $current->photos_count,
                ] : null,
                'totals' => $totals,
                'prizeTiers' => PhotoCompetition::prizeTiers(),
                'schedule' => PhotoCompetition::schedule(),
                'extraSlot' => PhotoCompetition::extraSlotSettings(),
            ],
        ]);
    }

    /**
     * Paginated competition history, most recent first, each with its
     * ranked winners (however many positions were actually paid out).
     */
    public function recent(Request $request): JsonResponse
    {
        $competitions = PhotoCompetition::query()
            ->withCount('photos')
            ->with('winners.user')
            ->latest('starts_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $competitions->getCollection()->map(fn(PhotoCompetition $competition) => [
                'id' => $competition->id,
                'startsAt' => $competition->starts_at,
                'endsAt' => $competition->ends_at,
                'status' => $competition->status,
                'photosCount' => $competition->photos_count,
                'winners' => $competition->winners->map(fn(PhotoCompetitionWinner $winner) => [
                    'id' => $winner->id,
                    'position' => $winner->position,
                    'userName' => $winner->user?->name,
                    'userPhone' => $winner->user?->phone,
                    'prizeAmount' => $winner->prize_amount,
                    'prizePaidAt' => $winner->prize_paid_at,
                    'kopokopoReference' => $winner->kopokopo_reference,
                ])->values(),
            ]),
            'meta' => [
                'current_page' => $competitions->currentPage(),
                'last_page' => $competitions->lastPage(),
                'total' => $competitions->total(),
            ],
        ]);
    }

    /**
     * Paginated history of extra-slot purchase attempts (pending, paid, and
     * failed), most recent first, each with the buyer and the week they
     * bought into.
     */
    public function slotPurchases(Request $request): JsonResponse
    {
        $purchases = PhotoSlotPurchase::query()
            ->with(['user', 'competition'])
            ->latest('created_at')
            ->paginate($request->integer('per_page', 10));

        return response()->json([
            'data' => $purchases->getCollection()->map(fn(PhotoSlotPurchase $purchase) => [
                'id' => $purchase->id,
                'userName' => $purchase->user?->name,
                'userPhone' => $purchase->user?->phone,
                'competitionStartsAt' => $purchase->competition?->starts_at,
                'amount' => $purchase->amount,
                'status' => $purchase->status,
                'createdAt' => $purchase->created_at,
                'paidAt' => $purchase->paid_at,
            ])->values(),
            'meta' => [
                'current_page' => $purchases->currentPage(),
                'last_page' => $purchases->lastPage(),
                'total' => $purchases->total(),
            ],
        ]);
    }

    /**
     * Set the KES prize for each of the top 10 positions, used going
     * forward by every future EndPhotoCompetition run.
     */
    public function updatePrizeTiers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'prizeTiers' => 'required|array|min:1|max:10',
            'prizeTiers.*' => 'required|integer|min:0',
        ]);

        $tiers = array_pad(array_slice($data['prizeTiers'], 0, 10), 10, 0);

        Setting::query()->updateOrCreate(
            ['key' => 'photo_prize_tiers'],
            ['value' => $tiers]
        );

        return response()->json(['data' => ['prizeTiers' => $tiers]]);
    }

    /**
     * Toggle whether users can buy an extra photo-submission slot, and set
     * its KES price.
     */
    public function updateExtraSlot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'price' => 'required|integer|min:0',
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'photo_extra_slot'],
            ['value' => $data]
        );

        return response()->json(['data' => $data]);
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
     * Directly edit the currently active competition's end time — for
     * extending/shortening this week's challenge without waiting for the
     * scheduler.
     */
    public function updateActive(Request $request): JsonResponse
    {
        $competition = PhotoCompetition::active()->withCount('photos')->first();

        if (! $competition) {
            throw ValidationException::withMessages([
                'endsAt' => 'There is no active competition to edit.',
            ]);
        }

        $data = $request->validate([
            'endsAt' => 'required|date|after:' . $competition->starts_at,
        ]);

        $competition->update(['ends_at' => $data['endsAt']]);

        return response()->json([
            'data' => [
                'id' => $competition->id,
                'endsAt' => $competition->ends_at,
                'photosCount' => $competition->photos_count,
            ],
        ]);
    }

    /**
     * Pay one position's prize to its winning photo's submitter via
     * Kopokopo M-Pesa.
     */
    public function payWinner(PhotoCompetitionWinner $winner): JsonResponse
    {
        [$status, $message, $data] = $this->kopokopoTransferService->payWinner($winner);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $status ? ['kopokopoReference' => $winner->fresh()->kopokopo_reference] : $data,
        ]);
    }

    private function minutesFromTime(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoCompetitionResource;
use App\Http\Resources\PhotoResource;
use App\Http\Services\PhotoCompetitionService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoCompetitionController extends Controller
{
    public function __construct(protected PhotoCompetitionService $photoCompetitionService)
    {
        // 
    }

    /**
     * The active competition and its photos, most-liked first.
     */
    public function current(Request $request): JsonResponse
    {
        $competition = $this->photoCompetitionService->current($request);
        $isActive = $competition?->status === PhotoCompetition::STATUS_ACTIVE;

        return response()->json([
            'data' => $competition ? new PhotoCompetitionResource($competition) : null,
            'nextStartsAt' => $isActive ? null : PhotoCompetition::nextScheduledStart()->toIso8601String(),
            'prizeTiers' => PhotoCompetition::prizeTiers(),
            'extraSlot' => $this->extraSlotState($isActive ? $competition : null, $request),
        ]);
    }

    /**
     * The extra-slot feature's global on/off + price, plus (only once a
     * competition is active) this viewer's own purchase state for it.
     */
    private function extraSlotState(
        ?PhotoCompetition $activeCompetition,
        Request $request
    ): array {
        $settings = PhotoCompetition::extraSlotSettings();

        $state = [
            'enabled' => $settings['enabled'],
            'price' => $settings['price'],
            'hasPaidExtraSlot' => false,
            'pendingPurchaseId' => null,
        ];

        if (! $activeCompetition) {
            return $state;
        }

        $purchase = PhotoSlotPurchase::where('competition_id', $activeCompetition->id)
            ->where('user_id', $request->user()->id)
            ->latest('created_at')
            ->first();

        if ($purchase) {
            $state['hasPaidExtraSlot'] = $purchase->status === PhotoSlotPurchase::STATUS_PAID;
            $state['pendingPurchaseId'] = $purchase->status === PhotoSlotPurchase::STATUS_PENDING
                ? $purchase->id
                : null;
        }

        return $state;
    }

    /**
     * Discovery grid: photos from ended competitions, latest first.
     */
    public function discover(Request $request): JsonResponse
    {
        $photos = Photo::query()
            ->whereHas('competition', fn($query) => $query->where('status', PhotoCompetition::STATUS_ENDED))
            ->with('user')
            ->withLikedByViewer($request->user())
            ->withIsWinner()
            ->latest('created_at')
            ->paginate(30);

        return response()->json([
            'data' => PhotoResource::collection($photos),
            'meta' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeaderboardEntryResource;
use App\Http\Resources\PhotoCompetitionResource;
use App\Http\Resources\PhotoResource;
use App\Http\Services\PhotoCompetitionService;
use App\Http\Services\PhotoPurchaseService;
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
        $state = $this->photoCompetitionService->currentState($request);

        return response()->json([
            'data' => $state['competition'] ? new PhotoCompetitionResource($state['competition']) : null,
            'nextStartsAt' => $state['nextStartsAt'],
            'prizeTiers' => $state['prizeTiers'],
            'extraSlot' => $state['extraSlot'],
            'extraPhoto' => $state['extraPhoto'],
        ]);
    }

    /**
     * Discovery grid: photos from ended competitions, latest first.
     */
    public function discover(Request $request): JsonResponse
    {
        $photos = $this->photoCompetitionService->discover($request);

        return response()->json([
            'data' => PhotoResource::collection($photos),
            'sale' => PhotoPurchaseService::publicSaleSettings(),
            'meta' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
            ],
        ]);
    }

    /**
     * Users who have posted, ranked by how often they placed 1st, then 2nd, etc.
     */
    public function leaderboard(): JsonResponse
    {
        $users = $this->photoCompetitionService->leaderboard();

        return response()->json([
            'data' => $users->getCollection()->values()->map(
                fn($user, int $index) => new LeaderboardEntryResource($user, $users->firstItem() + $index)
            ),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }
}

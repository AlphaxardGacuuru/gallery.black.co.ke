<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoCompetitionResource;
use App\Http\Resources\PhotoResource;
use App\Http\Services\PhotoCompetitionService;
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
            'meta' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
            ],
        ]);
    }
}

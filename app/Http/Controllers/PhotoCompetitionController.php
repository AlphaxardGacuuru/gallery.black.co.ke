<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoCompetitionResource;
use App\Http\Resources\PhotoResource;
use App\Http\Services\PhotoCompetitionService;
use App\Models\Photo;
use App\Models\PhotoCompetition;
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

        return response()->json([
            'data' => $competition ? new PhotoCompetitionResource($competition) : null,
            'nextStartsAt' => $competition ? null : PhotoCompetition::nextScheduledStart()->toIso8601String(),
        ]);
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

<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoCompetitionResource;
use App\Http\Resources\PhotoResource;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoCompetitionController extends Controller
{
    /**
     * The active competition and its photos, most-liked first.
     */
    public function current(Request $request): JsonResponse
    {
        $competition = PhotoCompetition::active()
            ->with(['photos' => function ($query) use ($request) {
                $query->with('user')
                    ->withLikedByViewer($request->user())
                    ->orderByDesc('likes_count')
                    ->orderBy('created_at');
            }])
            ->first();

        return response()->json([
            'data' => $competition ? new PhotoCompetitionResource($competition) : null,
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

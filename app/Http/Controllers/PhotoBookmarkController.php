<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoResource;
use App\Http\Services\PhotoBookmarkService;
use App\Http\Services\PhotoPurchaseService;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoBookmarkController extends Controller
{
    public function __construct(protected PhotoBookmarkService $service) {}

    /**
     * The signed-in user's saved photos, for the Saved tab on their profile.
     */
    public function index(Request $request): JsonResponse
    {
        $photos = $this->service->index($request->user());

        return response()->json([
            'data' => PhotoResource::collection($photos),
            'sale' => PhotoPurchaseService::publicSaleSettings(),
            'meta' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
            ],
        ]);
    }

    public function store(Request $request, Photo $photo): JsonResponse
    {
        $this->service->store($request->user(), $photo);

        return response()->json([
            'data' => ['bookmarked' => true],
        ]);
    }

    public function destroy(Request $request, Photo $photo): JsonResponse
    {
        $this->service->destroy($request->user(), $photo);

        return response()->json([
            'data' => ['bookmarked' => false],
        ]);
    }
}

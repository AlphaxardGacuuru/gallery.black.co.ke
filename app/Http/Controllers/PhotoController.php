<?php

namespace App\Http\Controllers;

use App\Http\Resources\PhotoResource;
use App\Http\Services\PhotoService;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoController extends Controller
{
    public function __construct(protected PhotoService $photoService)
    {
        //
    }

    /**
     * A single photo's full-size view (any competitor's, active or ended),
     * plus this viewer's permissions for it, since those depend on whether
     * its competition is still active.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $result = $this->photoService->show($request, $id);

        return response()->json([
            'data' => new PhotoResource($result['photo']),
            'canDelete' => $result['canDelete'],
            'canLike' => $result['canLike'],
        ]);
    }

    /**
     * Submit a photo (already uploaded via FilePond) into the active competition.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'temporaryUploadIds' => 'required|array|min:1|max:' . Photo::MAX_IMAGES_PER_ENTRY,
            'temporaryUploadIds.*' => 'integer|distinct|exists:temporary_uploads,id',
            'caption' => 'required|string|max:280',
        ]);

        $photo = $this->photoService->store($request, $data);

        return response()->json([
            'data' => new PhotoResource($photo),
        ], 201);
    }

    /**
     * Add more photos to an already-submitted entry (e.g. after buying an
     * extra slot), while its competition is still active.
     */
    public function storeImages(Request $request, Photo $photo): JsonResponse
    {
        $data = $request->validate([
            'temporaryUploadIds' => 'required|array|min:1|max:' . Photo::MAX_IMAGES_PER_ENTRY,
            'temporaryUploadIds.*' => 'integer|distinct|exists:temporary_uploads,id',
        ]);

        $photo = $this->photoService->addImages($request, $photo, $data['temporaryUploadIds']);

        return response()->json([
            'data' => new PhotoResource($photo),
        ], 201);
    }

    /**
     * Delete the authenticated user's own photo (while its competition is active).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->photoService->destroy($request, $id);

        return response()->json(['data' => null]);
    }
}

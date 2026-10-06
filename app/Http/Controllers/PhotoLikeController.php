<?php

namespace App\Http\Controllers;

use App\Events\PhotoLiked;
use App\Http\Services\PhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoLikeController extends Controller
{
    public function __construct(protected PhotoService $photoService)
    {
        //
    }

    /**
     * Like a photo on the authenticated user's behalf. A like is permanent,
     * calling this again for a photo already liked by this user is a no-op,
     * not an unlike (see PhotoService::like).
     */
    public function store(Request $request, string $id): JsonResponse
    {
        ['photo' => $photo, 'liked' => $liked] = $this->photoService->like($request, $id);

        if ($liked) {
            broadcast(new PhotoLiked($photo))->toOthers();
        }

        return response()->json([
            'data' => [
                'photoId' => $photo->id,
                'likesCount' => $photo->likes_count,
                'isLikedByViewer' => true,
            ],
        ]);
    }
}

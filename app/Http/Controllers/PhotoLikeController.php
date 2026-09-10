<?php

namespace App\Http\Controllers;

use App\Events\PhotoLiked;
use App\Models\Photo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhotoLikeController extends Controller
{
    /**
     * Toggle the authenticated user's like on a photo.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $photo = Photo::findOrFail($id);
        $user = $request->user();

        $like = $photo->likes()->where('user_id', $user->id)->first();

        if ($like) {
            $like->delete();
            $photo->decrement('likes_count');
        } else {
            $photo->likes()->create(['user_id' => $user->id]);
            $photo->increment('likes_count');
        }

        $photo->refresh();

        broadcast(new PhotoLiked($photo))->toOthers();

        return response()->json([
            'data' => [
                'photoId' => $photo->id,
                'likesCount' => $photo->likes_count,
                'isLikedByViewer' => ! $like,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Events\PhotoLiked;
use App\Models\Photo;
use App\Models\PhotoCompetition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PhotoLikeController extends Controller
{
    /**
     * Like a photo on the authenticated user's behalf. A like is permanent —
     * calling this again for a photo already liked by this user is a no-op,
     * not an unlike, so it's safe to call idempotently (e.g. a retried or
     * duplicate request never decrements likes_count).
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $photo = Photo::with('competition')->findOrFail($id);
        $user = $request->user();

        if ($photo->competition->status !== PhotoCompetition::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'id' => 'Likes are frozen once a challenge ends.',
            ]);
        }

        $alreadyLiked = $photo->likes()->where('user_id', $user->id)->exists();

        if (! $alreadyLiked) {
            $photo->likes()->create(['user_id' => $user->id]);
            $photo->increment('likes_count');
            $photo->refresh();

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

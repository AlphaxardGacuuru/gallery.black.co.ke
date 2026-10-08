<?php

namespace App\Http\Controllers\Admin;

use App\Events\PhotoLikedEvent;
use App\Http\Controllers\Controller;
use App\Models\Photo;
use App\Models\PhotoLike;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPhotoLikeController extends Controller
{
    /**
     * Users matching an optional name search, each annotated with whether
     * they already like this specific photo, the pool an admin picks from
     * in the "manage likes" dialog.
     */
    public function index(Request $request, Photo $photo): JsonResponse
    {
        $users = User::query()
            ->when(
                $request->filled('name'),
                fn($query) => $query->where('name', 'LIKE', '%' . $request->input('name') . '%')
            )
            ->addSelect([
                'likes_photo' => PhotoLike::query()
                    ->selectRaw('1')
                    ->whereColumn('user_id', 'users.id')
                    ->where('photo_id', $photo->id)
                    ->limit(1),
            ])
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'data' => $users->map(fn(User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'likesPhoto' => (bool) $user->likes_photo,
            ]),
        ]);
    }

    /**
     * Toggle whether the given user likes this photo, on the admin's
     * behalf. Unlike the public like endpoint (PhotoLikeController::store,
     * a one-way "vote" that freezes once the competition ends), this is a
     * moderation tool: it supports removing a like too, and isn't gated by
     * competition status.
     */
    public function toggle(Photo $photo, User $user): JsonResponse
    {
        $like = PhotoLike::where('photo_id', $photo->id)
            ->where('user_id', $user->id)
            ->first();

        if ($like) {
            $like->delete();
            $photo->decrement('likes_count');
        } else {
            PhotoLike::create(['photo_id' => $photo->id, 'user_id' => $user->id]);
            $photo->increment('likes_count');
        }

        $photo->refresh();
        broadcast(new PhotoLikedEvent($photo, $user, ! $like))->toOthers();

        return response()->json([
            'data' => [
                'userId' => $user->id,
                'liked' => ! $like,
                'likesCount' => $photo->likes_count,
            ],
        ]);
    }
}

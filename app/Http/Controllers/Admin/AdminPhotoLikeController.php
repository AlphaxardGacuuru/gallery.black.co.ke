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
     * Users matching an optional name search, seed filter ("seed" is
     * "only" or "exclude"), "liked" filter (only users who already like
     * this photo) and "flagged" filter, each annotated with whether they already like
     * this specific photo, the pool an admin picks from in the "manage
     * likes" dialog.
     */
    public function index(Request $request, Photo $photo): JsonResponse
    {
        $request->validate([
            'seed' => 'nullable|in:only,exclude',
            'liked' => 'nullable|boolean',
            'flagged' => 'nullable|boolean',
        ]);

        $users = User::query()
            ->when(
                $request->filled('name'),
                fn($query) => $query->where('name', 'LIKE', '%' . $request->input('name') . '%')
            )
            ->when(
                $request->input('seed') === 'only',
                fn($query) => $query->where('is_seed', true)
            )
            ->when(
                $request->input('seed') === 'exclude',
                fn($query) => $query->real()
            )
            ->when(
                $request->boolean('liked'),
                fn($query) => $query->whereIn(
                    'id',
                    PhotoLike::query()
                        ->select('user_id')
                        ->where('photo_id', $photo->id)
                )
            )
            ->when(
                $request->boolean('flagged'),
                fn($query) => $query->whereNotNull('flagged_at')
            )
            ->addSelect([
                'likes_photo' => PhotoLike::query()
                    ->selectRaw('1')
                    ->whereColumn('user_id', 'users.id')
                    ->where('photo_id', $photo->id)
                    ->limit(1),
            ])
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 30));

        return response()->json([
            'data' => $users->getCollection()->map(fn(User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'verified' => (bool) $user->verified,
                'isSeed' => (bool) $user->is_seed,
                'isFlagged' => $user->isFlagged(),
                'likesPhoto' => (bool) $user->likes_photo,
            ]),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
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
        } else {
            PhotoLike::create(['photo_id' => $photo->id, 'user_id' => $user->id]);
        }

        // A recount rather than +/-1, since a flagged user's like is
        // stored but doesn't count.
        $photo->recountLikes();
        broadcast(new PhotoLikedEvent($photo, $user, ! $like))->toOthers();

        return response()->json([
            'data' => [
                'userId' => $user->id,
                'liked' => ! $like,
                'likesCount' => $photo->likes_count,
            ],
        ]);
    }

    /**
     * Delete every like on this photo from flagged users. Their likes
     * already don't count, this clears them out for good (e.g. once the
     * admin is sure they were fake).
     */
    public function destroyFlagged(Photo $photo): JsonResponse
    {
        $removed = $photo->likes()
            ->whereHas('user', fn($query) => $query->whereNotNull('flagged_at'))
            ->delete();

        $photo->recountLikes();

        return response()->json([
            'data' => [
                'removed' => $removed,
                'likesCount' => $photo->likes_count,
            ],
        ]);
    }
}

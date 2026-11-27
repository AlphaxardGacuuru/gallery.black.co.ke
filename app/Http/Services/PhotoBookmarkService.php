<?php

namespace App\Http\Services;

use App\Models\Photo;
use App\Models\PhotoBookmark;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class PhotoBookmarkService extends Service
{
	/**
	 * Photos $user saved, the most recently saved first.
	 */
	public function index(User $user): LengthAwarePaginator
	{
		return Photo::query()
			->join('photo_bookmarks', 'photo_bookmarks.photo_id', '=', 'photos.id')
			->where('photo_bookmarks.user_id', $user->id)
			->select('photos.*')
			->with(['user', 'images'])
			->withLikedByViewer($user)
			->withPurchasedByViewer($user)
			->withBookmarkedByViewer($user)
			->withIsWinner()
			->orderByDesc('photo_bookmarks.created_at')
			->paginate(30);
	}

	/**
	 * Save $photo for $user. Saving it twice keeps the one bookmark.
	 */
	public function store(User $user, Photo $photo): void
	{
		PhotoBookmark::query()
			->firstOrCreate(['user_id' => $user->id, 'photo_id' => $photo->id]);
	}

	public function destroy(User $user, Photo $photo): void
	{
		PhotoBookmark::query()
			->where('user_id', $user->id)
			->where('photo_id', $photo->id)
			->delete();
	}
}

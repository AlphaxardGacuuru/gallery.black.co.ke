<?php

namespace App\Http\Services;

use App\Enums\UserEventName;
use App\Models\Photo;
use App\Models\PhotoBookmark;
use App\Models\User;
use App\Services\EventRecorder;
use Illuminate\Pagination\LengthAwarePaginator;

class PhotoBookmarkService extends Service
{
	public function __construct(protected EventRecorder $eventRecorder)
	{
		parent::__construct();
	}

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
		$bookmark = PhotoBookmark::query()
			->firstOrCreate(['user_id' => $user->id, 'photo_id' => $photo->id]);

		if ($bookmark->wasRecentlyCreated) {
			$this->eventRecorder->record(UserEventName::PHOTO_BOOKMARKED, $user, photo: $photo);
		}
	}

	public function destroy(User $user, Photo $photo): void
	{
		$removed = PhotoBookmark::query()
			->where('user_id', $user->id)
			->where('photo_id', $photo->id)
			->delete();

		if ($removed) {
			$this->eventRecorder->record(UserEventName::PHOTO_UNBOOKMARKED, $user, photo: $photo);
		}
	}
}

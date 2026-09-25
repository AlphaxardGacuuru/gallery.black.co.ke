<?php

namespace App\Http\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Models\Photo;
use App\Models\PhotoCompetition;

class PhotoCompetitionService extends Service
{
	public function current(Request $request): ?PhotoCompetition
	{
		$active = PhotoCompetition::active()
			->with(['photos' => function ($query) use ($request) {
				$query->with('user')
					->withLikedByViewer($request->user())
					->orderByDesc('likes_count')
					->orderBy('created_at');
			}])
			->first();

		if ($active) {
			return $active;
		}

		return $this->mostRecentlyEnded($request);
	}

	/**
	 * The most recently ended competition, with its ranked winner photos
	 * loaded — kept on the compete page (with the winner treatment) until
	 * the next competition starts and takes over as the active one.
	 *
	 * Deliberately looks at the single latest-ended competition, not just
	 * the latest one that happens to have winners — a winnerless week (no
	 * entries, or every tier is unpaid) must show nothing rather than
	 * falling back to an older competition's winner, which would dangle a
	 * stale photo on the compete page well past when it actually won.
	 */
	protected function mostRecentlyEnded(Request $request): ?PhotoCompetition
	{
		$ended = PhotoCompetition::query()
			->where('status', PhotoCompetition::STATUS_ENDED)
			->latest('ends_at')
			->first();

		if (! $ended) {
			return null;
		}

		$winnerPhotoIds = $ended
			->winners()
			->pluck('photo_id')
			->filter()
			->values();

		if ($winnerPhotoIds->isEmpty()) {
			return null;
		}

		$photos = Photo::query()
			->whereIn('id', $winnerPhotoIds)
			->with('user')
			->withLikedByViewer($request->user())
			->withIsWinner()
			->get()
			->sortBy(fn(Photo $photo) => $winnerPhotoIds->search($photo->id))
			->values();

		$ended->setRelation('photos', $photos);

		return $ended;
	}

	public function discover(Request $request): LengthAwarePaginator
	{
		$photos = Photo::query()
			->whereHas('competition', fn($query) => $query->where('status', PhotoCompetition::STATUS_ENDED))
			->with('user')
			->withLikedByViewer($request->user())
			->withIsWinner()
			->latest('created_at')
			->paginate(30);

		return $photos;
	}
}

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
	 */
	protected function mostRecentlyEnded(Request $request): ?PhotoCompetition
	{
		$ended = PhotoCompetition::query()
			->where('status', PhotoCompetition::STATUS_ENDED)
			->whereHas('winners')
			->latest('ends_at')
			->first();

		if (! $ended) {
			return null;
		}

		$winnerPhotoIds = $ended->winners()->pluck('photo_id')->filter()->values();

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

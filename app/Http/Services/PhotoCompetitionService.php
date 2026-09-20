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
		$competition = PhotoCompetition::active()
			->with(['photos' => function ($query) use ($request) {
				$query->with('user')
					->withLikedByViewer($request->user())
					->orderByDesc('likes_count')
					->orderBy('created_at');
			}])
			->first();

		return $competition;
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

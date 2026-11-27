<?php

namespace App\Http\Services;

use App\Models\Photo;
use App\Models\PhotoCompetitionWinner;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UserProfileService extends Service
{
	public function __construct(protected PhotoCompetitionService $photoCompetitionService)
	{
		parent::__construct();
	}

	/**
	 * $user's public profile: who they are and how they've done in the
	 * challenges. Cash won counts every prize they placed for, paid out or
	 * not yet.
	 */
	public function profile(User $user): array
	{
		return [
			'id' => $user->id,
			'name' => $user->name,
			'avatar' => $user->avatar,
			'verified' => $user->verified,
			'joinedAt' => Carbon::parse($user->getRawOriginal('created_at'))->toIso8601String(),
			'stats' => [
				'posts' => $user->photos()->count(),
				'likes' => (int) $user->photos()->sum('likes_count'),
				'cashWon' => (int) PhotoCompetitionWinner::query()
					->where('user_id', $user->id)
					->sum('prize_amount'),
				'leaderboardPosition' => $this->photoCompetitionService->leaderboardPosition($user),
			],
		];
	}

	/**
	 * $user's posts, newest first, from every challenge including the one
	 * running now.
	 */
	public function photos(Request $request, User $user): LengthAwarePaginator
	{
		return Photo::query()
			->where('user_id', $user->id)
			->with(['user', 'images'])
			->withLikedByViewer($request->user())
			->withPurchasedByViewer($request->user())
			->withBookmarkedByViewer($request->user())
			->withIsWinner()
			->latest('created_at')
			->paginate(30);
	}
}

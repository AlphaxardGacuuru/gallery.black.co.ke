<?php

namespace App\Http\Resources;

use App\Http\Services\PhotoCompetitionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of the leaderboard. Deliberately exposes only public profile
 * fields (unlike UserResource, which carries email and phone).
 */
class LeaderboardEntryResource extends JsonResource
{
	public function __construct($resource, protected int $rank)
	{
		parent::__construct($resource);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(Request $request): array
	{
		// How many times the user finished at each position, index 0 = 1st
		// place (the same convention as PhotoCompetition::prizeTiers()).
		$placements = array_map(
			fn(int $position) => (int) $this->{"position_{$position}_count"},
			range(1, PhotoCompetitionService::LEADERBOARD_POSITIONS)
		);

		return [
			'rank' => $this->rank,
			'userId' => $this->id,
			'userName' => $this->name,
			'userAvatar' => $this->avatar,
			'userVerified' => (bool) $this->verified,
			'photosCount' => (int) $this->photos_count,
			'placements' => $placements,
		];
	}
}

<?php

namespace App\Events;

use App\Models\PhotoCompetition;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PhotoCompetitionEnded implements ShouldBroadcast
{
	use Dispatchable;

	public function __construct(public PhotoCompetition $competition) {}

	/**
	 * @return array<int, Channel>
	 */
	public function broadcastOn(): array
	{
		return [
			new Channel('photo-competition.' . $this->competition->id),
		];
	}

	public function broadcastAs(): string
	{
		return 'competition.ended';
	}

	public function broadcastWith(): array
	{
		return [
			'competitionId' => $this->competition->id,
			'winners' => $this->competition->winners->map(fn($winner) => [
				'position' => $winner->position,
				'photoId' => $winner->photo_id,
			])->all(),
		];
	}
}

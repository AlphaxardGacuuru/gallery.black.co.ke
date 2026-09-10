<?php

namespace App\Events;

use App\Models\Photo;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Queued (not ShouldBroadcastNow) so a broadcaster hiccup never blocks the
// like/unlike request itself.
class PhotoLiked implements ShouldBroadcast
{
	use Dispatchable;

	public function __construct(public Photo $photo) {}

	/**
	 * @return array<int, Channel>
	 */
	public function broadcastOn(): array
	{
		return [
			new Channel('photo-competition.' . $this->photo->competition_id),
		];
	}

	public function broadcastAs(): string
	{
		return 'photo.liked';
	}

	public function broadcastWith(): array
	{
		return [
			'photoId' => $this->photo->id,
			'likesCount' => $this->photo->likes_count,
		];
	}
}

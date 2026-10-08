<?php

namespace App\Events;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Queued (not ShouldBroadcastNow) so a broadcaster hiccup never blocks the
// like/unlike request itself.
class PhotoLikedEvent implements ShouldBroadcast
{
	use Dispatchable;

	/**
	 * @param  bool  $liked  True when this call added a new like, false when
	 *                       it removed one (the admin "manage likes" tool can
	 *                       do either; PhotoLikedListener uses
	 *                       this to never notify on a removal).
	 */
	public function __construct(
		public Photo $photo,
		public User $likedBy,
		public bool $liked = true,
	) {}

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

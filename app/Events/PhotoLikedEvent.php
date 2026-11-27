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

	/** Liked with the heart in the app. */
	public const SOURCE_APP = 'app';

	/** Liked from the button on a push notification. */
	public const SOURCE_PUSH_ACTION = 'push_action';

	/** Added or removed by an admin from "manage likes". */
	public const SOURCE_ADMIN = 'admin';

	/** Added by the seed-likes command. */
	public const SOURCE_SEED = 'seed';

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
		public string $source = self::SOURCE_APP,
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

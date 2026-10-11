<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
	public function toArray(Request $request): array
	{
		return [
			'id' => $this->id,
			'competitionId' => $this->competition_id,
			'userId' => $this->user_id,
			'userName' => $this->whenLoaded('user', fn() => $this->user->name),
			'userAvatar' => $this->whenLoaded('user', fn() => $this->user->avatar),
			'userVerified' => $this->whenLoaded('user', fn() => (bool) $this->user->verified, false),
			'url' => $this->url,
			'thumbnailUrl' => $this->thumbnail_url,
			'caption' => $this->caption,
			'width' => $this->width,
			'height' => $this->height,
			'aspectRatio' => $this->aspect_ratio,
			'images' => $this->whenLoaded(
				'images',
				fn() => $this->images->map(fn($image) => [
					'id' => $image->id,
					'url' => $image->url,
					'thumbnailUrl' => $image->thumbnail_url,
					'width' => $image->width,
					'height' => $image->height,
				])->values(),
				[]
			),
			'likesCount' => $this->likes_count,
			'isLikedByViewer' => (bool) ($this->is_liked_by_viewer ?? false),
			'isWinner' => (bool) ($this->is_winner ?? false),
			'position' => $this->position !== null ? (int) $this->position : null,
			'createdAt' => $this->created_at,
		];
	}
}

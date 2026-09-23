<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoCompetitionResource extends JsonResource
{
	public function toArray(Request $request): array
	{
		return [
			'id' => $this->id,
			'startsAt' => $this->starts_at,
			'endsAt' => $this->ends_at,
			'status' => $this->status,
			'photos' => PhotoResource::collection($this->whenLoaded('photos')),
		];
	}
}

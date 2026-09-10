<?php

namespace Database\Factories;

use App\Models\PhotoLike;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoLike>
 */
class PhotoLikeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photo_id' => \App\Models\Photo::factory(),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}

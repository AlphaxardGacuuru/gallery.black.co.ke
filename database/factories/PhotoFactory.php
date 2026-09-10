<?php

namespace Database\Factories;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => \App\Models\PhotoCompetition::factory(),
            'user_id' => \App\Models\User::factory(),
            'disk' => 'public',
            'path' => 'photos/' . $this->faker->uuid() . '.jpg',
            'caption' => $this->faker->optional()->sentence(),
            'width' => 1080,
            'height' => $this->faker->randomElement([1080, 1350, 720]),
            'likes_count' => 0,
        ];
    }
}

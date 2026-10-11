<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\PhotoImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoImage>
 */
class PhotoImageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photo_id' => Photo::factory(),
            'disk' => 'public',
            'path' => 'photos/' . $this->faker->uuid() . '.jpg',
            'width' => 1080,
            'height' => $this->faker->randomElement([1080, 1350, 720]),
            'position' => 1,
        ];
    }
}

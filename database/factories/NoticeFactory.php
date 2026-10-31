<?php

namespace Database\Factories;

use App\Models\Notice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(3),
            'title' => fake()->sentence(3),
            'body' => fake()->sentence(),
            'icon' => 'megaphone',
            'primary_label' => 'Learn more',
            'primary_url' => '/discover',
            'secondary_label' => 'Close',
            'audience' => Notice::AUDIENCE_ALL,
            'priority' => 0,
            'cooldown_hours' => 24,
            'max_impressions' => null,
            'stop_after_click' => true,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }
}

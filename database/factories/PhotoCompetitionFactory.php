<?php

namespace Database\Factories;

use App\Models\PhotoCompetition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoCompetition>
 */
class PhotoCompetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->startOfWeek();

        return [
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDays(4)->setTime(20, 0),
            'status' => PhotoCompetition::STATUS_ACTIVE,
            'prize_amount' => 500,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn() => ['status' => PhotoCompetition::STATUS_ENDED]);
    }
}

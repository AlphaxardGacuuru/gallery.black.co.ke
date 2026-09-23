<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\PhotoCompetition;
use App\Models\PhotoCompetitionWinner;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoCompetitionWinner>
 */
class PhotoCompetitionWinnerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'competition_id' => PhotoCompetition::factory(),
            'photo_id' => Photo::factory(),
            'user_id' => User::factory(),
            'position' => 1,
            'prize_amount' => 500,
            'prize_paid_at' => null,
        ];
    }
}

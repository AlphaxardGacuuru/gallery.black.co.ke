<?php

namespace Database\Factories;

use App\Models\PhotoCompetition;
use App\Models\PhotoSlotPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoSlotPurchase>
 */
class PhotoSlotPurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'competition_id' => PhotoCompetition::factory(),
            'amount' => 50,
            'status' => PhotoSlotPurchase::STATUS_PENDING,
            'kopokopo_reference' => null,
            'mpesa_transaction_id' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn() => [
            'status' => PhotoSlotPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Models\Photo;
use App\Models\PhotoPurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoPurchase>
 */
class PhotoPurchaseFactory extends Factory
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
            'buyer_id' => User::factory(),
            'seller_id' => fn(array $attributes) => Photo::find($attributes['photo_id'])?->user_id,
            'amount' => 200,
            'seller_amount' => 140,
            'status' => PhotoPurchase::STATUS_PENDING,
            'kopokopo_reference' => null,
            'mpesa_transaction_id' => null,
            'paid_at' => null,
            'payout_reference' => null,
            'payout_paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn() => [
            'status' => PhotoPurchase::STATUS_PAID,
            'paid_at' => now(),
        ]);
    }
}

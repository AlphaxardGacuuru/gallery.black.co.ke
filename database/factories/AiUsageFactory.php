<?php

namespace Database\Factories;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
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
            'feature' => AiUsage::FEATURE_SUPPORT_REPLY,
            'provider' => 'gemini',
            'model' => 'gemini-3.1-flash-lite',
            'attempt' => null,
            'input_tokens' => fake()->numberBetween(3000, 8000),
            'cached_input_tokens' => 0,
            'output_tokens' => fake()->numberBetween(50, 300),
            'reasoning_tokens' => 0,
            'cost_micros' => fake()->numberBetween(800, 2500),
        ];
    }
}

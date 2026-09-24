<?php

namespace Database\Factories;

use App\Karat;
use App\Models\GoldRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoldRate>
 */
class GoldRateFactory extends Factory
{
    /**
     * @return array{karat: int, rate_per_gram: float, effective_date: string, created_by: Factory}
     */
    public function definition(): array
    {
        return [
            'karat' => fake()->randomElement(Karat::cases())->value,
            'rate_per_gram' => fake()->randomFloat(2, 1000, 250000),
            'effective_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'created_by' => User::factory(),
        ];
    }
}

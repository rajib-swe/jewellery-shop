<?php

namespace Database\Factories;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\User;
use App\StockMovementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * @return array{item_id: int, type: string, ref_type: null, ref_id: null, weight: float, note: string|null, user_id: int}
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'type' => StockMovementType::In->value,
            'ref_type' => null,
            'ref_id' => null,
            'weight' => fake()->randomFloat(3, 0.1, 100),
            'note' => fake()->optional()->sentence(),
            'user_id' => User::factory(),
        ];
    }
}

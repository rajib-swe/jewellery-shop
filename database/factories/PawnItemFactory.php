<?php

namespace Database\Factories;

use App\Models\Pawn;
use App\Models\PawnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PawnItem>
 */
class PawnItemFactory extends Factory
{
    /**
     * @return array{pawn_id: int, description: string, karat: int, gross_weight: float, stone_weight: float, net_weight: float, estimated_value: float, photo: null, category_id: null, item_id: null}
     */
    public function definition(): array
    {
        $grossWeight = fake()->randomFloat(3, 1, 60);

        return [
            'pawn_id' => Pawn::factory(),
            'description' => fake()->randomElement([
                'স্বর্ণের নেকলেস',
                'স্বর্ণের চুড়ি (জোড়া)',
                'স্বর্ণের আংটি',
                'হাতার্কাঠিনো নকশী চুড়ি',
                'স্বর্ণের চেইন',
            ]),
            'karat' => fake()->randomElement([18, 21, 22, 24]),
            'gross_weight' => $grossWeight,
            'stone_weight' => 0,
            'net_weight' => $grossWeight,
            'estimated_value' => fake()->randomFloat(2, 20000, 900000),
            'photo' => null,
            'category_id' => null,
            'item_id' => null,
        ];
    }
}

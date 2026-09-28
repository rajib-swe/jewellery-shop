<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    /**
     * @return array{purchase_id: int, item_id: null, tag_no: string, name: string, karat: int, gross_weight: float, stone_weight: float, net_weight: float, rate: float, making_value: float, amount: float}
     */
    public function definition(): array
    {
        $grossWeight = fake()->randomFloat(3, 1, 60);
        $rate = fake()->randomFloat(2, 9000, 14000);

        return [
            'purchase_id' => Purchase::factory(),
            'item_id' => null,
            'tag_no' => 'ITM-'.fake()->unique()->regexify('[A-Z0-9]{6}'),
            'name' => fake()->randomElement([
                'স্বর্ণের নেকলেস',
                'স্বর্ণের চুড়ি',
                'স্বর্ণের আংটি',
                'হাতার্কাঠিনো নকশী চুড়ি',
            ]),
            'karat' => fake()->randomElement([18, 21, 22, 24]),
            'gross_weight' => $grossWeight,
            'stone_weight' => 0,
            'net_weight' => $grossWeight,
            'rate' => $rate,
            'making_value' => 0,
            'amount' => $grossWeight * $rate,
        ];
    }
}

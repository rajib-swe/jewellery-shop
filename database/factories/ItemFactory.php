<?php

namespace Database\Factories;

use App\ItemStatus;
use App\Karat;
use App\MakingType;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Item>
 */
class ItemFactory extends Factory
{
    /**
     * @return array{tag_no: string, category_id: int, name: string, karat: int, gross_weight: float, stone_weight: float, net_weight: float, making_type: string, making_value: float, stone_price: float, status: string, image: null, barcode: string|null}
     */
    public function definition(): array
    {
        $grossWeight = fake()->randomFloat(3, 1, 100);
        $stoneWeight = fake()->randomFloat(3, 0, min(5, $grossWeight));

        return [
            'tag_no' => 'ITM-'.Str::ulid()->toBase32(),
            'category_id' => Category::factory(),
            'name' => fake()->randomElement(['Ring', 'Necklace', 'Bangle', 'Bracelet', 'Earrings']),
            'karat' => fake()->randomElement(Karat::cases())->value,
            'gross_weight' => $grossWeight,
            'stone_weight' => $stoneWeight,
            'net_weight' => round($grossWeight - $stoneWeight, 3),
            'making_type' => fake()->randomElement(MakingType::cases())->value,
            'making_value' => fake()->randomFloat(2, 0, 5000),
            'stone_price' => fake()->randomFloat(2, 0, 10000),
            'status' => ItemStatus::InStock->value,
            'image' => null,
            'barcode' => fake()->boolean(70) ? fake()->unique()->bothify('BC-########') : null,
        ];
    }
}

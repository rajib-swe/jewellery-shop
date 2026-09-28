<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * @return array{purchase_no: string, supplier_id: int, date: string, subtotal: float, discount: float, total: float, paid: float, due: float, notes: null, user_id: int}
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 10000, 400000);

        return [
            'purchase_no' => 'PUR-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'supplier_id' => Supplier::factory(),
            'date' => now()->toDateString(),
            'subtotal' => $subtotal,
            'discount' => 0,
            'total' => $subtotal,
            'paid' => 0,
            'due' => $subtotal,
            'notes' => null,
            'user_id' => User::factory(),
        ];
    }
}

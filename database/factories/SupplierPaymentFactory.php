<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierPayment>
 */
class SupplierPaymentFactory extends Factory
{
    /**
     * @return array{supplier_id: int, purchase_id: null, amount: float, date: string, method: string, reference: null, note: null, user_id: int}
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'purchase_id' => null,
            'amount' => fake()->randomFloat(2, 500, 100000),
            'date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => null,
            'note' => null,
            'user_id' => User::factory(),
        ];
    }
}

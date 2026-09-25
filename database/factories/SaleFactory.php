<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\SaleStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * @return array{invoice_no: string, customer_id: int, date: string, subtotal: float, discount: float, vat: float, exchange_amount: float, total: float, paid: float, due: float, status: string, notes: null, user_id: int}
     */
    public function definition(): array
    {
        $subtotal = fake()->randomFloat(2, 1000, 100000);
        $paid = fake()->boolean(60) ? $subtotal : fake()->randomFloat(2, 0, $subtotal);

        return [
            'invoice_no' => 'INV-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'date' => now()->toDateString(),
            'subtotal' => $subtotal,
            'discount' => 0,
            'vat' => 0,
            'exchange_amount' => 0,
            'total' => $subtotal,
            'paid' => min($paid, $subtotal),
            'due' => max($subtotal - $paid, 0),
            'status' => SaleStatus::Completed->value,
            'notes' => null,
            'user_id' => User::factory(),
        ];
    }
}

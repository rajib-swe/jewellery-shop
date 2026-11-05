<?php

namespace Database\Factories;

use App\CashSourceType;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTransaction>
 */
class CashTransactionFactory extends Factory
{
    /**
     * @return array{source_type: string, source_id: ?string, direction: string, amount: float, method: string, date: string, reference: null, note: null, user_id: int}
     */
    public function definition(): array
    {
        return [
            'source_type' => CashSourceType::CashAdjustment->value,
            'source_id' => null,
            'direction' => 'in',
            'amount' => fake()->randomFloat(2, 100, 50000),
            'method' => 'cash',
            'date' => now()->toDateString(),
            'reference' => null,
            'note' => null,
            'user_id' => User::factory(),
        ];
    }

    public function outgoing(): static
    {
        return $this->state(fn (): array => ['direction' => 'out']);
    }
}

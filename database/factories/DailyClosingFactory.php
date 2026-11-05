<?php

namespace Database\Factories;

use App\Models\DailyClosing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyClosing>
 */
class DailyClosingFactory extends Factory
{
    /**
     * @return array{date: string, opening_balance: float, total_in: float, total_out: float, closing_balance: float, closed_by: null, closed_at: null, reopened_by: null, reopened_at: null, note: null}
     */
    public function definition(): array
    {
        $opening = fake()->randomFloat(2, 10000, 200000);
        $totalIn = fake()->randomFloat(2, 5000, 150000);
        $totalOut = fake()->randomFloat(2, 1000, 50000);

        return [
            'date' => now()->toDateString(),
            'opening_balance' => $opening,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'closing_balance' => $opening + $totalIn - $totalOut,
            'closed_by' => null,
            'closed_at' => null,
            'reopened_by' => null,
            'reopened_at' => null,
            'note' => null,
        ];
    }
}

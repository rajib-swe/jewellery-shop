<?php

namespace Database\Factories;

use App\Models\Pawn;
use App\Models\PawnPayment;
use App\Models\User;
use App\PawnPaymentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PawnPayment>
 */
class PawnPaymentFactory extends Factory
{
    /**
     * @return array{pawn_id: int, type: string, amount: float, date: string, method: string, reference: null, note: null, user_id: int}
     */
    public function definition(): array
    {
        return [
            'pawn_id' => Pawn::factory(),
            'type' => PawnPaymentType::Interest->value,
            'amount' => fake()->randomFloat(2, 100, 5000),
            'date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => null,
            'note' => null,
            'user_id' => User::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Pawn;
use App\Models\User;
use App\PawnInterestType;
use App\PawnStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pawn>
 */
class PawnFactory extends Factory
{
    /**
     * @return array{pawn_no: string, customer_id: int, date: string, term_start_date: string, principal: float, interest_rate: float, interest_type: string, due_date: string, status: string, notes: null, user_id: int}
     */
    public function definition(): array
    {
        $date = now()->startOfDay();

        return [
            'pawn_no' => 'PWN-'.now()->format('Y').'-'.fake()->unique()->numerify('######'),
            'customer_id' => Customer::factory(),
            'date' => $date->toDateString(),
            'term_start_date' => $date->toDateString(),
            'principal' => fake()->randomFloat(2, 5000, 150000),
            'interest_rate' => '2.00',
            'interest_type' => PawnInterestType::Simple->value,
            'due_date' => $date->copy()->addDays(30)->toDateString(),
            'status' => PawnStatus::Active->value,
            'notes' => null,
            'user_id' => User::factory(),
        ];
    }
}

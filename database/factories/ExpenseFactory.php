<?php

namespace Database\Factories;

use App\ExpenseCategory;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array{category: string, title: string, amount: float, date: string, method: string, reference: null, note: null, user_id: int}
     */
    public function definition(): array
    {
        return [
            'category' => ExpenseCategory::Other->value,
            'title' => fake()->randomElement([
                'দোকানের ভাড়া',
                'বিদ্যুৎ বিল',
                'কারিগর মজুরি',
                'পরিবহন খরচ',
                'ট্যাক্স ও ফি',
            ]),
            'amount' => fake()->randomFloat(2, 500, 25000),
            'date' => now()->toDateString(),
            'method' => 'cash',
            'reference' => null,
            'note' => null,
            'user_id' => User::factory(),
        ];
    }
}

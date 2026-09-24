<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array{code: string, name: string, phone: string, nid: string, address: string, photo: null, opening_balance: float, notes: string|null}
     */
    public function definition(): array
    {
        return [
            'code' => 'CUS-'.Str::ulid()->toBase32(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('01#########'),
            'nid' => fake()->unique()->numerify('##########'),
            'address' => fake()->address(),
            'photo' => null,
            'opening_balance' => fake()->randomFloat(2, 0, 10000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}

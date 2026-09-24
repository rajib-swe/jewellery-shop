<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $phone = config('customers.seed_phone');

        if (! is_string($phone) || trim($phone) === '') {
            return;
        }

        Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => config('customers.seed_name', 'Demo Customer'),
                'opening_balance' => 0,
            ],
        );
    }
}

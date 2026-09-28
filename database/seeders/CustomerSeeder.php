<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $demoCustomers = [
            [
                'name' => 'মোহাম্মদ করিম',
                'phone' => '01811-000001',
                'address' => 'বাড়ি ১২, রোড ৩, ধানমন্ডি, ঢাকা',
                'nid' => '1990123456789',
                'opening_balance' => 0,
            ],
            [
                'name' => 'আনোয়ার হোসেন',
                'phone' => '01711-000002',
                'address' => 'টুঙ্গিপাড়া, গোপালগঞ্জ',
                'nid' => '1985987654321',
                'opening_balance' => 0,
            ],
            [
                'name' => 'মরিয়ম আক্তার',
                'phone' => '01911-000003',
                'address' => 'মেইন রোড, মুরাদনগর, কুমিল্লা',
                'nid' => '1995555444333',
                'opening_balance' => 0,
            ],
        ];

        $phone = config('customers.seed_phone');
        if (is_string($phone) && trim($phone) !== '') {
            $demoCustomers[] = [
                'name' => config('customers.seed_name', 'Demo Customer'),
                'phone' => $phone,
                'address' => 'ঢাকা, বাংলাদেশ',
                'opening_balance' => 0,
            ];
        }

        foreach ($demoCustomers as $customerData) {
            Customer::firstOrCreate(
                ['phone' => $customerData['phone']],
                [
                    'code' => 'CUS-'.Str::ulid()->toBase32(),
                    ...$customerData,
                ],
            );
        }
    }
}

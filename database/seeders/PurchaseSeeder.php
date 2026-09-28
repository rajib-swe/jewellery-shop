<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Database\Seeder;

/**
 * Demo purchases for local UI testing.
 *
 * The gold rates only exist for today, so every purchase is dated today and the
 * history spread comes from the ledger rather than from backdating, which would
 * need a rate row for every past date.
 */
class PurchaseSeeder extends Seeder
{
    public function run(PurchaseService $purchases): void
    {
        $admin = User::role('admin')->first();

        if ($admin === null) {
            return;
        }

        $categories = Category::query()->pluck('id', 'name');
        $ringCategory = $categories['Ring'] ?? $categories->first();
        $bangleCategory = $categories['Bangle'] ?? $categories->first();
        $necklaceCategory = $categories['Necklace'] ?? $categories->first();

        $karigor = Supplier::query()->where('type', 'karigor')->first();
        $supplier = Supplier::query()->where('type', 'supplier')->first();

        // Karigor drop, fully settled on the counter.
        if ($karigor !== null) {
            $purchases->create([
                'supplier_id' => $karigor->id,
                'date' => today()->toDateString(),
                'discount' => '1500.00',
                'notes' => 'কারিগরের নতুন লট, সাথে সাথে নগদ পরিশোধ।',
                'items' => [
                    [
                        'name' => 'হাতার্কাঠিনো নকশী চুড়ি',
                        'category_id' => $bangleCategory,
                        'karat' => 22,
                        'gross_weight' => '16.400',
                        'stone_weight' => '0.400',
                        'making_value' => '2500.00',
                    ],
                    [
                        'name' => 'স্বর্ণের আংটি',
                        'category_id' => $ringCategory,
                        'karat' => 21,
                        'gross_weight' => '5.200',
                    ],
                ],
                'payment' => ['method' => 'cash', 'amount' => '250000.00'],
            ], $admin);
        }

        // Karigor drop on credit, part paid later.
        if ($karigor !== null) {
            $purchase = $purchases->create([
                'supplier_id' => $karigor->id,
                'date' => today()->toDateString(),
                'notes' => 'বাকি পরিশোধ চলছে।',
                'items' => [
                    [
                        'name' => 'স্বর্ণের নেকলেস সেট',
                        'category_id' => $necklaceCategory,
                        'karat' => 22,
                        'gross_weight' => '24.600',
                        'stone_weight' => '0.600',
                    ],
                    [
                        'name' => 'কানপাশা দুল',
                        'category_id' => $ringCategory,
                        'karat' => 22,
                        'gross_weight' => '6.800',
                    ],
                ],
                'payment' => ['method' => 'bkash', 'amount' => '150000.00', 'reference' => 'TRX-BK551200'],
            ], $admin);

            if ($purchase !== null) {
                $purchases->addPayment($karigor, [
                    'amount' => '100000.00',
                    'date' => today()->toDateString(),
                    'method' => 'cash',
                    'note' => 'দ্বিতীয় কিস্তি',
                ], $admin);
            }
        }

        // Supplier import, unpaid so the ledger shows a full balance.
        if ($supplier !== null) {
            $purchases->create([
                'supplier_id' => $supplier->id,
                'date' => today()->toDateString(),
                'notes' => 'হাঁদাবাজার থেকে আনা মাল, এখনো পরিশোধ হয়নি।',
                'items' => [
                    [
                        'name' => 'স্বর্ণের চুড়ি (ভারী)',
                        'category_id' => $bangleCategory,
                        'karat' => 21,
                        'gross_weight' => '28.400',
                    ],
                    [
                        'name' => 'গোল্ড বাউটি চুড়ি',
                        'category_id' => $bangleCategory,
                        'karat' => 22,
                        'gross_weight' => '19.800',
                    ],
                    [
                        'name' => 'ছেলেদের চেইন',
                        'category_id' => $necklaceCategory,
                        'karat' => 22,
                        'gross_weight' => '12.100',
                    ],
                ],
            ], $admin);
        }
    }
}

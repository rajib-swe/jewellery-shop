<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Pawn;
use App\Models\User;
use App\Services\PawnService;
use Illuminate\Database\Seeder;

/**
 * Demo pawn accounts for local UI testing.
 *
 * Every branch of the lifecycle is represented once, with a different ledger, so
 * the list filters, the interest breakdown and each print button can be checked
 * without having to record anything by hand.
 */
class PawnSeeder extends Seeder
{
    public function run(PawnService $pawns): void
    {
        if (Pawn::query()->exists()) {
            return;
        }

        $admin = User::role('admin')->first()
            ?? User::role('manager')->first();

        if ($admin === null) {
            return;
        }

        $customers = Customer::query()->orderBy('id')->get();
        $categories = Category::query()->pluck('id', 'name');
        $ringCategory = $categories['Ring'] ?? $categories->first();
        $necklaceCategory = $categories['Necklace'] ?? $categories->first();

        $pick = static fn (int $index) => $customers[$index % max($customers->count(), 1)] ?? null;

        $create = function (array $data) use ($pawns, $admin): ?Pawn {
            return $pawns->create($data, $admin);
        };

        // 1. Fresh pawn, no payment yet: the interest runs from day one.
        $fresh = $create([
            'customer_id' => $pick(0)?->id,
            'date' => today()->subDays(5)->toDateString(),
            'principal' => '50000.00',
            'interest_rate' => '2.00',
            'notes' => 'গ্রাহক স্বর্ণের নেকলেস বন্ধক দিয়েছেন, পরের মাসে ফেরত নিতে চাইবেন।',
            'items' => [
                [
                    'description' => 'স্বর্ণের নেকলেস (হাতা)',
                    'karat' => 22,
                    'gross_weight' => '18.600',
                    'stone_weight' => '0.600',
                    'estimated_value' => '320000.00',
                    'category_id' => $necklaceCategory,
                ],
            ],
        ]);

        // 2. Interest only paid: the principal is untouched, interest keeps growing.
        $interestOnly = $create([
            'customer_id' => $pick(1)?->id,
            'date' => today()->subDays(40)->toDateString(),
            'principal' => '40000.00',
            'interest_rate' => '2.50',
            'notes' => 'শুধু সুদ জমা দিয়েছেন, মূলধন অপরিবর্তিত।',
            'items' => [
                [
                    'description' => 'স্বর্ণের চুড়ি (জোড়া)',
                    'karat' => 21,
                    'gross_weight' => '14.200',
                    'stone_weight' => '0.000',
                    'estimated_value' => '240000.00',
                    'category_id' => $ringCategory,
                ],
            ],
        ]);

        if ($interestOnly !== null) {
            $pawns->addPayment($interestOnly, [
                'amount' => '1000.00',
                'date' => today()->subDays(10)->toDateString(),
                'method' => 'cash',
                'note' => 'প্রথম মাসের সুদ',
            ], $admin);
        }

        // 3. Part principal repaid: future interest is lower, accrued interest is not.
        $partial = $create([
            'customer_id' => $pick(2)?->id,
            'date' => today()->subDays(75)->toDateString(),
            'principal' => '80000.00',
            'interest_rate' => '2.00',
            'notes' => 'আংশিক মূলধন পরিশোধ করেছেন।',
            'items' => [
                [
                    'description' => 'স্বর্ণের আংটি (হাতা)',
                    'karat' => 22,
                    'gross_weight' => '7.400',
                    'stone_weight' => '0.200',
                    'estimated_value' => '175000.00',
                    'category_id' => $ringCategory,
                ],
                [
                    'description' => 'হাতার্কাঠিনো নকশী চুড়ি',
                    'karat' => 18,
                    'gross_weight' => '9.100',
                    'stone_weight' => '0.000',
                    'estimated_value' => '96000.00',
                    'category_id' => $necklaceCategory,
                ],
            ],
        ]);

        if ($partial !== null) {
            $pawns->addPayment($partial, [
                'amount' => '1500.00',
                'date' => today()->subDays(45)->toDateString(),
                'method' => 'bkash',
                'reference' => 'TRX-BK220011',
            ], $admin);

            $pawns->addPayment($partial, [
                'amount' => '30000.00',
                'date' => today()->subDays(20)->toDateString(),
                'method' => 'cash',
                'note' => 'আংশিক মূলধন পরিশোধ',
            ], $admin);
        }

        // 4. Overdue pawn: past the due date, so the list filter and the badge show.
        $overdue = $create([
            'customer_id' => $pick(0)?->id,
            'date' => today()->subDays(95)->toDateString(),
            'principal' => '65000.00',
            'interest_rate' => '3.00',
            'notes' => 'মেয়াদ পেরিয়ে গেছে, ফেরতের অনুরোধ করা হয়েছে।',
            'items' => [
                [
                    'description' => 'স্বর্ণের চেইন',
                    'karat' => 22,
                    'gross_weight' => '22.800',
                    'stone_weight' => '0.000',
                    'estimated_value' => '395000.00',
                    'category_id' => $necklaceCategory,
                ],
            ],
        ]);

        if ($overdue !== null) {
            $overdue->update(['due_date' => today()->subDays(12)->toDateString()]);
        }

        // 5. Renewed pawn: the interest was settled and the term restarted.
        $renewed = $create([
            'customer_id' => $pick(1)?->id,
            'date' => today()->subDays(70)->toDateString(),
            'principal' => '35000.00',
            'interest_rate' => '2.00',
            'notes' => 'সুদ পরিশোধ করে মেয়াদ নবায়ন করেছেন।',
            'items' => [
                [
                    'description' => 'কানপাশা দুল',
                    'karat' => 21,
                    'gross_weight' => '6.100',
                    'stone_weight' => '0.000',
                    'estimated_value' => '105000.00',
                    'category_id' => $ringCategory,
                ],
            ],
        ]);

        if ($renewed !== null) {
            $pawns->renew($renewed, [
                'date' => today()->subDays(20)->toDateString(),
                'term_days' => 30,
            ], $admin);
        }

        // 6. Redeemed pawn: closed with a single redemption payment, releases the goods.
        $redeemed = $create([
            'customer_id' => $pick(2)?->id,
            'date' => today()->subDays(60)->toDateString(),
            'principal' => '45000.00',
            'interest_rate' => '2.00',
            'notes' => 'সম্পূর্ণ পরিশোধ করে পণ্য ফেরত নিয়েছেন।',
            'items' => [
                [
                    'description' => 'স্বর্ণের নেকলেস সেট',
                    'karat' => 22,
                    'gross_weight' => '16.400',
                    'stone_weight' => '0.400',
                    'estimated_value' => '290000.00',
                    'category_id' => $necklaceCategory,
                ],
            ],
        ]);

        if ($redeemed !== null) {
            $pawns->redeem($redeemed, [
                'date' => today()->subDays(5)->toDateString(),
                'method' => 'cash',
                'note' => 'সুদসহ মূলধন পরিশোধিত',
            ], $admin);
        }

        // 7. Forfeited pawn: past the grace period, the goods became shop stock.
        $forfeited = $create([
            'customer_id' => $pick(0)?->id,
            'date' => today()->subDays(200)->toDateString(),
            'principal' => '90000.00',
            'interest_rate' => '2.00',
            'notes' => 'ছুটির মেয়াদ শেষ, পণ্য দোকানের স্ক্র্যাপ হিসেবে যুক্ত হয়েছে।',
            'items' => [
                [
                    'description' => 'স্বর্ণের চুড়ি (জোড়া, ভাঙা)',
                    'karat' => 22,
                    'gross_weight' => '19.500',
                    'stone_weight' => '0.000',
                    'estimated_value' => '340000.00',
                    'category_id' => $ringCategory,
                ],
            ],
        ]);

        if ($forfeited !== null) {
            $forfeited->update(['due_date' => today()->subDays(150)->toDateString()]);
            $pawns->forfeit($forfeited, [
                'reason' => 'ছুটির মেয়াদ শেষ হওয়ার পরও পরিশোধ না হওয়ায় জপত।',
                'move_to_inventory' => true,
                'item_status' => 'scrap',
            ], $admin);
        }
    }
}

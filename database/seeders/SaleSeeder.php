<?php

namespace Database\Seeders;

use App\ItemStatus;
use App\Models\Customer;
use App\Models\GoldRate;
use App\Models\Item;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(SaleService $sales): void
    {
        if (Sale::query()->exists()) {
            return;
        }

        $admin = User::role('admin')->first();

        if ($admin === null) {
            return;
        }

        // Ensure gold rates exist for today
        $defaultRates = [
            24 => '14000.00',
            22 => '12800.00',
            21 => '12200.00',
            18 => '10500.00',
        ];

        foreach ($defaultRates as $karat => $rate) {
            GoldRate::firstOrCreate(
                [
                    'karat' => $karat,
                    'effective_date' => today()->toDateString(),
                ],
                [
                    'rate_per_gram' => $rate,
                    'created_by' => $admin->id,
                ],
            );
        }

        $customers = Customer::all();
        $c1 = $customers->get(0);
        $c2 = $customers->get(1) ?? $c1;
        $c3 = $customers->get(2) ?? $c1;

        $inStockItems = Item::query()
            ->where('status', ItemStatus::InStock)
            ->get();

        if ($inStockItems->count() < 4) {
            return;
        }

        // Sale 1: Fully Paid Sale (2 items)
        $item1 = $inStockItems[0];
        $item2 = $inStockItems[1];

        $rate1 = (float) ($defaultRates[$item1->karat] ?? 12800);
        $rate2 = (float) ($defaultRates[$item2->karat] ?? 12800);
        $line1 = ($item1->net_weight * $rate1) + $item1->making_value + $item1->stone_price;
        $line2 = ($item2->net_weight * $rate2) + $item2->making_value + $item2->stone_price;
        $total1 = round($line1 + $line2, 2);

        $sales->create([
            'customer_id' => $c1?->id,
            'date' => today()->toDateString(),
            'notes' => 'নগদ পরিশোধকৃত চালান (Customer Copy)',
            'items' => [
                ['item_id' => $item1->id],
                ['item_id' => $item2->id],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => (string) $total1, 'reference' => 'CSH-001'],
            ],
        ], $admin, true);

        // Sale 2: Sale with Old Gold Exchange + Partial Payment + Due
        $item3 = $inStockItems[2];
        $rate3 = (float) ($defaultRates[$item3->karat] ?? 12800);
        $line3 = ($item3->net_weight * $rate3) + $item3->making_value + $item3->stone_price;
        $exchangeVal = 6.000 * 11500.00; // 69,000
        $discount2 = 3000.00;
        $subtotalAfterDisc = max(0, $line3 - $discount2 - $exchangeVal);
        $partialPay = round($subtotalAfterDisc * 0.6, 2);

        $sale2 = $sales->create([
            'customer_id' => $c2?->id,
            'date' => today()->toDateString(),
            'discount' => (string) $discount2,
            'notes' => 'পুরাতন স্বর্ণ বিনিময় ও বকেয়াসহ বিক্রয়',
            'items' => [
                ['item_id' => $item3->id],
            ],
            'exchanges' => [
                [
                    'description' => 'পুরাতন স্বর্ণের চেইন',
                    'karat' => 21,
                    'weight' => '6.000',
                    'rate' => '11500.00',
                ],
            ],
            'payments' => [
                ['method' => 'bkash', 'amount' => (string) $partialPay, 'reference' => 'TRX-BK778899'],
            ],
        ], $admin, true);

        // Record a second payment towards Sale 2 due
        if ($sale2->due > 1000) {
            $sales->addPayment($sale2, [
                'method' => 'cash',
                'amount' => '1000.00',
                'reference' => 'DUE-RCP-01',
            ], $admin);
        }

        // Sale 3: Sale with Handwritten Custom Line
        $sales->create([
            'customer_id' => $c3?->id,
            'date' => today()->toDateString(),
            'notes' => 'হাতে লেখা কাস্টম পণ্য বিক্রয়',
            'items' => [
                [
                    'name' => 'নকশী চুড়ি (হাতে লেখা কাস্টম)',
                    'karat' => 22,
                    'weight' => '8.500',
                    'rate' => '12800.00',
                    'making_type' => 'fixed',
                    'making_value' => '2500.00',
                    'stone_price' => '0.00',
                ],
            ],
            'payments' => [
                ['method' => 'card', 'amount' => (string) (8.5 * 12800 + 2500), 'reference' => 'VISA-4412'],
            ],
        ], $admin, true);

        // Sale 4: Voided Sale
        $item4 = $inStockItems[3];
        $rate4 = (float) ($defaultRates[$item4->karat] ?? 12800);
        $total4 = round(($item4->net_weight * $rate4) + $item4->making_value, 2);

        $sale4 = $sales->create([
            'customer_id' => $c1?->id,
            'date' => today()->toDateString(),
            'notes' => 'বাতিলকৃত চালান নমুনা',
            'items' => [
                ['item_id' => $item4->id],
            ],
            'payments' => [
                ['method' => 'cash', 'amount' => (string) $total4],
            ],
        ], $admin, true);

        $sales->void($sale4, $admin, 'গ্রাহকের বিশেষ অনুরোধে তাৎক্ষণিক বাতিল');
    }
}

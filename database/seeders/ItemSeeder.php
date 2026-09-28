<?php

namespace Database\Seeders;

use App\ItemStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(StockService $stock): void
    {
        $admin = User::role('admin')->first();

        if ($admin === null) {
            return;
        }

        $categories = Category::query()->pluck('id', 'name');

        if ($categories->isEmpty()) {
            return;
        }

        $items = [
            [
                'name' => 'স্বর্ণের নেকলেস',
                'category_id' => $categories['Necklace'] ?? $categories->first(),
                'karat' => 22,
                'gross_weight' => '15.500',
                'stone_weight' => '0.000',
                'making_type' => 'fixed',
                'making_value' => '4500.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-NK-2201',
            ],
            [
                'name' => 'ব্রাইডাল নেকলেস সেট',
                'category_id' => $categories['Necklace'] ?? $categories->first(),
                'karat' => 22,
                'gross_weight' => '24.200',
                'stone_weight' => '0.500',
                'making_type' => 'per_gram',
                'making_value' => '450.00',
                'stone_price' => '3500.00',
                'barcode' => 'BC-NK-2202',
            ],
            [
                'name' => 'স্বর্ণের চুড়ি (জোড়া)',
                'category_id' => $categories['Bangle'] ?? $categories->first(),
                'karat' => 21,
                'gross_weight' => '12.000',
                'stone_weight' => '0.000',
                'making_type' => 'fixed',
                'making_value' => '3000.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-BN-2101',
            ],
            [
                'name' => 'গোল্ড বাউটি চুড়ি',
                'category_id' => $categories['Bangle'] ?? $categories->first(),
                'karat' => 22,
                'gross_weight' => '18.500',
                'stone_weight' => '0.000',
                'making_type' => 'per_gram',
                'making_value' => '400.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-BN-2202',
            ],
            [
                'name' => 'স্বর্ণের আংটি',
                'category_id' => $categories['Ring'] ?? $categories->first(),
                'karat' => 21,
                'gross_weight' => '4.800',
                'stone_weight' => '0.000',
                'making_type' => 'fixed',
                'making_value' => '1500.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-RG-2101',
            ],
            [
                'name' => 'ডায়মন্ড কাট আংটি',
                'category_id' => $categories['Ring'] ?? $categories->first(),
                'karat' => 18,
                'gross_weight' => '3.200',
                'stone_weight' => '0.200',
                'making_type' => 'fixed',
                'making_value' => '1200.00',
                'stone_price' => '2000.00',
                'barcode' => 'BC-RG-1801',
            ],
            [
                'name' => 'কানপাশা দুল',
                'category_id' => $categories['Earrings'] ?? $categories->first(),
                'karat' => 22,
                'gross_weight' => '6.400',
                'stone_weight' => '0.000',
                'making_type' => 'fixed',
                'making_value' => '2000.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-ER-2201',
            ],
            [
                'name' => 'ঝুমকা দুল',
                'category_id' => $categories['Earrings'] ?? $categories->first(),
                'karat' => 21,
                'gross_weight' => '8.200',
                'stone_weight' => '0.000',
                'making_type' => 'per_gram',
                'making_value' => '350.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-ER-2102',
            ],
            [
                'name' => 'হালকা সোনার চেইন',
                'category_id' => $categories['Other'] ?? $categories->first(),
                'karat' => 22,
                'gross_weight' => '5.000',
                'stone_weight' => '0.000',
                'making_type' => 'fixed',
                'making_value' => '1800.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-CN-2201',
            ],
            [
                'name' => 'ছেলেদের চেইন',
                'category_id' => $categories['Other'] ?? $categories->first(),
                'karat' => 22,
                'gross_weight' => '11.600',
                'stone_weight' => '0.000',
                'making_type' => 'per_gram',
                'making_value' => '380.00',
                'stone_price' => '0.00',
                'barcode' => 'BC-CN-2202',
            ],
        ];

        foreach ($items as $data) {
            $existing = Item::query()->where('barcode', $data['barcode'])->first();

            if (! $existing) {
                $stock->createInbound($data, $admin, null, ItemStatus::InStock);
            }
        }
    }
}

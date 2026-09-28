<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\SupplierType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'আব্দুল খালেক কারিগর',
                'type' => SupplierType::Karigor->value,
                'phone' => '01711-000101',
                'address' => 'বাজার পাটোয়াখালী, ঢাকা',
                'notes' => 'নকশী চুড়ি ও আংটির কারিগর, সাপ্লাই নিয়মিত।',
            ],
            [
                'name' => 'জসিম উদ্দিন কারিগর',
                'type' => SupplierType::Karigor->value,
                'phone' => '01811-000102',
                'address' => 'উত্তরা পল্টন, ঢাকা',
                'notes' => 'ভাঙা সোনা কিনে নেকলেস তৈরি করেন।',
            ],
            [
                'name' => 'রহমান জুয়েলার্স',
                'type' => SupplierType::Supplier->value,
                'phone' => '01911-000103',
                'address' => 'গোপালগঞ্জ সদর',
                'nid' => '1985123456789',
                'notes' => 'হাঁদাবাজার থেকে নিয়মিত মাল আনে।',
            ],
            [
                'name' => 'গোল্ড হাউস ট্রেডার্স',
                'type' => SupplierType::Supplier->value,
                'phone' => '01611-000104',
                'address' => 'কুমিল্লা নিউ মার্কেট',
                'notes' => 'বাল্ক দামে সরাসরি আমদানি।',
            ],
        ];

        foreach ($suppliers as $supplier) {
            // The root seeder runs with WithoutModelEvents, so the model's
            // creating hook that mints the code never fires here.
            Supplier::firstOrCreate(
                ['phone' => $supplier['phone']],
                [...$supplier, 'code' => 'SUP-'.Str::ulid()->toBase32()],
            );
        }
    }
}

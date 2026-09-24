<?php

namespace Database\Seeders;

use App\Karat;
use App\Models\GoldRate;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GoldRateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $adminId = User::role('admin')->value('id');

        if ($adminId === null) {
            return;
        }

        foreach (Karat::cases() as $karat) {
            $rate = config("gold-rates.initial_rates.{$karat->value}");

            if ($rate === null || $rate === '') {
                continue;
            }

            GoldRate::firstOrCreate(
                [
                    'karat' => $karat->value,
                    'effective_date' => today()->toDateString(),
                ],
                [
                    'rate_per_gram' => $rate,
                    'created_by' => $adminId,
                ],
            );
        }
    }
}

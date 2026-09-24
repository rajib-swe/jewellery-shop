<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingsService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (SettingsService::defaults() as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => (string) $value],
            );
        }
    }
}

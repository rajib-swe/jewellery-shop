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
        $defaults = array_merge(SettingsService::defaults(), [
            'default_pawn_interest_rate' => '2.00',
            'pawn_terms' => implode("\n", [
                '১. বন্ধকর মেয়াদ শুরু হবে টিকিটের তারিখ থেকে এবং চলবে সেটিংসে নির্ধারিত দিন সংখ্যা পর্যন্ত।',
                '২. সুদ সরল হিসাবে প্রতি মাসে অবশিষ্ট মূলধনের উপর প্রযোজ্য হবে; মাসের ভাগ হিসাব দোকানের নিয়ম অনুযায়ী হবে।',
                '৩. প্রতিটি পেমেন্ট আগে বকেয়া সুদ এবং পরে মূলধনের বিপরীতে সমন্বয় হবে।',
                '৪. নির্ধারিত তারিখে সুদ ও মূলধন পরিশোধ না করলে টাকাটি সুদসহ মূলধন পরিশোধের জন্য বণ্ডি চালু থাকবে।',
                '৫. সুদসহ সম্পূর্ণ টাকা পরিশোধ করলে পণ্যগুলো গ্রাহককে ফেরত দেওয়া হবে এবং টিকিট বাতিল হবে।',
                '৬. নির্ধারিত তারিখ পেরিয়ে সেটিংসে দেওয়া ছুটির দিন শেষ হওয়ার পরও পরিশোধ না হলে দোকান বণ্ডিগৃহিত পণ্য বিক্রয় বা গলানোর অধিকার রাখবে।',
                '৭. এই টিকিটের কোনো অংশ কেটে নেওয়া বা হাতে লেখা পরিবর্তন গ্রহণযোগ্য হবে না।',
            ]),
        ]);

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => (string) $value],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $categories = [
            'Ring' => 'Rings and wedding bands',
            'Necklace' => 'Necklaces and pendants',
            'Bangle' => 'Bangles and bracelets',
            'Earrings' => 'Earrings and studs',
            'Other' => 'Other jewellery items',
        ];

        foreach ($categories as $name => $description) {
            Category::firstOrCreate(
                ['name' => $name],
                ['description' => $description],
            );
        }
    }
}

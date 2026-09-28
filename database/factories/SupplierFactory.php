<?php

namespace Database\Factories;

use App\Models\Supplier;
use App\SupplierType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * @return array{code: string, name: string, type: string, phone: ?string, address: ?string, nid: ?string, notes: null}
     */
    public function definition(): array
    {
        return [
            'code' => 'SUP-'.Str::ulid()->toBase32(),
            'name' => fake()->randomElement([
                'আব্দুল খালেক কারিগর',
                'রহমান জুয়েলার্স',
                'সেলিম উদ্দিন',
                'গোল্ড হাউস ট্রেডার্স',
                'জসিম উদ্দিন কারিগর',
            ]),
            'type' => fake()->randomElement(SupplierType::cases())->value,
            'phone' => '01'.fake()->numerify('9##-######'),
            'address' => fake()->randomElement([
                'বাজার পাটোয়াখালী, ঢাকা',
                'উত্তরা পল্টন, ঢাকা',
                'গোপালগঞ্জ সদর',
                'কুমিল্লা নিউ মার্কেট',
            ]),
            'nid' => null,
            'notes' => null,
        ];
    }
}

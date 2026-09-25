<?php

namespace Database\Factories;

use App\Models\DocumentCounter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentCounter>
 */
class DocumentCounterFactory extends Factory
{
    /**
     * @return array{scope: string, year: int, value: int}
     */
    public function definition(): array
    {
        return [
            'scope' => 'invoice',
            'year' => (int) now()->format('Y'),
            'value' => 0,
        ];
    }
}

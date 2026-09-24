<?php

namespace Tests\Unit;

use App\Support\Weight;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class WeightTest extends TestCase
{
    public function test_unit_ratios_are_exact(): void
    {
        $this->assertEqualsWithDelta(11.664, Weight::voriToGrams(1), 0.000000000001);
        $this->assertEqualsWithDelta(0.729, Weight::anaToGrams(1), 0.000000000001);
        $this->assertEqualsWithDelta(0.1215, Weight::rotiToGrams(1), 0.000000000001);
        $this->assertEqualsWithDelta(0.01215, Weight::pointToGrams(1), 0.000000000001);
        $this->assertEqualsWithDelta(23.328, Weight::voriToGrams('2'), 0.000000000001);
    }

    public function test_grams_round_trip_through_each_unit_without_drift(): void
    {
        $grams = 10.137;

        $this->assertEqualsWithDelta($grams, Weight::voriToGrams(Weight::gramsToVori($grams)), 0.0000000001);
        $this->assertEqualsWithDelta($grams, Weight::anaToGrams(Weight::gramsToAna($grams)), 0.0000000001);
        $this->assertEqualsWithDelta($grams, Weight::rotiToGrams(Weight::gramsToRoti($grams)), 0.0000000001);
        $this->assertEqualsWithDelta($grams, Weight::pointToGrams(Weight::gramsToPoint($grams)), 0.0000000001);
    }

    public function test_traditional_units_can_be_combined(): void
    {
        $this->assertEqualsWithDelta(
            13.5351,
            Weight::traditionalToGrams(vori: 1, ana: 2, roti: 3, point: 4),
            0.000000000001,
        );
    }

    public function test_grams_can_be_expressed_in_each_traditional_unit(): void
    {
        $units = Weight::gramsToTraditional(11.664);

        $this->assertSame(1.0, $units['vori']);
        $this->assertSame(16.0, $units['ana']);
        $this->assertSame(96.0, $units['roti']);
        $this->assertSame(960.0, $units['point']);
    }

    public function test_negative_weights_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Weight::gramsToVori(-0.001);
    }
}

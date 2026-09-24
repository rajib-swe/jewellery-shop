<?php

namespace App\Support;

use InvalidArgumentException;

final class Weight
{
    public const VORI_IN_GRAMS = 11.664;

    public const ANA_PER_VORI = 16;

    public const ROTI_PER_ANA = 6;

    public const POINT_PER_ROTI = 10;

    public const ANA_IN_GRAMS = self::VORI_IN_GRAMS / self::ANA_PER_VORI;

    public const ROTI_IN_GRAMS = self::ANA_IN_GRAMS / self::ROTI_PER_ANA;

    public const POINT_IN_GRAMS = self::ROTI_IN_GRAMS / self::POINT_PER_ROTI;

    public static function gramsToVori(float $grams): float
    {
        return self::fromGrams($grams, self::VORI_IN_GRAMS);
    }

    public static function voriToGrams(float $vori): float
    {
        return self::toGrams($vori, self::VORI_IN_GRAMS, 'vori');
    }

    public static function gramsToAna(float $grams): float
    {
        return self::fromGrams($grams, self::ANA_IN_GRAMS);
    }

    public static function anaToGrams(float $ana): float
    {
        return self::toGrams($ana, self::ANA_IN_GRAMS, 'ana');
    }

    public static function gramsToRoti(float $grams): float
    {
        return self::fromGrams($grams, self::ROTI_IN_GRAMS);
    }

    public static function rotiToGrams(float $roti): float
    {
        return self::toGrams($roti, self::ROTI_IN_GRAMS, 'roti');
    }

    public static function gramsToPoint(float $grams): float
    {
        return self::fromGrams($grams, self::POINT_IN_GRAMS);
    }

    public static function pointToGrams(float $point): float
    {
        return self::toGrams($point, self::POINT_IN_GRAMS, 'point');
    }

    /**
     * @return array{vori: float, ana: float, roti: float, point: float}
     */
    public static function gramsToTraditional(float $grams): array
    {
        return [
            'vori' => self::gramsToVori($grams),
            'ana' => self::gramsToAna($grams),
            'roti' => self::gramsToRoti($grams),
            'point' => self::gramsToPoint($grams),
        ];
    }

    public static function traditionalToGrams(
        float $vori = 0,
        float $ana = 0,
        float $roti = 0,
        float $point = 0,
    ): float {
        return round(
            self::voriToGrams($vori)
            + self::anaToGrams($ana)
            + self::rotiToGrams($roti)
            + self::pointToGrams($point),
            12,
        );
    }

    private static function fromGrams(float $grams, float $gramsPerUnit): float
    {
        self::assertNonNegative($grams, 'grams');

        return round($grams / $gramsPerUnit, 12);
    }

    private static function toGrams(float $value, float $gramsPerUnit, string $unit): float
    {
        self::assertNonNegative($value, $unit);

        return round($value * $gramsPerUnit, 12);
    }

    private static function assertNonNegative(float $value, string $unit): void
    {
        if (! is_finite($value) || $value < 0) {
            throw new InvalidArgumentException("Weight in {$unit} cannot be negative or non-finite.");
        }
    }
}

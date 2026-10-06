<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Canonical weight: integer points (1 point = 0.01215 g exactly).
 *
 * 1 ভরি = 960 পয়েন্ট = 11.664 g
 * 1 আনা = 60 পয়েন্ট
 * 1 রতি = 10 পয়েন্ট
 *
 * Grams are never stored; they are always derived from points.
 */
final class WeightPoints
{
    public const POINTS_PER_VORI = 960;

    public const POINTS_PER_ANA = 60;

    public const POINTS_PER_ROTI = 10;

    public const GRAMS_PER_POINT = '0.01215';

    public static function toGrams(int|string|null $points): string
    {
        if ($points === null) {
            return '0.00000';
        }

        return bcmul((string) $points, self::GRAMS_PER_POINT, 5);
    }

    public static function fromGrams(string|float|int|null $grams): int
    {
        if ($grams === null || $grams === '') {
            return 0;
        }

        $value = (string) $grams;

        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException('Weight in grams cannot be negative or non-numeric.');
        }

        // Round half up: points = floor(grams / 0.01215 + 0.5)
        return (int) bcdiv(bcadd($value, bcmul('0.5', self::GRAMS_PER_POINT, 10), 10), self::GRAMS_PER_POINT, 0);
    }

    public static function fromVori(string|float|int $vori): int
    {
        return self::fromTraditionalUnit($vori, self::POINTS_PER_VORI, 'vori');
    }

    public static function fromAna(string|float|int $ana): int
    {
        return self::fromTraditionalUnit($ana, self::POINTS_PER_ANA, 'ana');
    }

    public static function fromRoti(string|float|int $roti): int
    {
        return self::fromTraditionalUnit($roti, self::POINTS_PER_ROTI, 'roti');
    }

    public static function fromPoint(string|float|int $point): int
    {
        $value = (string) $point;

        if (! is_numeric($value) || (float) $value < 0) {
            throw new InvalidArgumentException('Weight in point cannot be negative or non-numeric.');
        }

        return (int) bcdiv(bcadd($value, '0.5', 10), '1', 0);
    }

    public static function fromParts(
        string|float|int $vori = 0,
        string|float|int $ana = 0,
        string|float|int $roti = 0,
        string|float|int $point = 0,
    ): int {
        return self::fromVori($vori) + self::fromAna($ana) + self::fromRoti($roti) + self::fromPoint($point);
    }

    /**
     * @return array{vori: int, ana: int, roti: int, point: int}
     */
    public static function toParts(int $points): array
    {
        $vori = intdiv($points, self::POINTS_PER_VORI);
        $rest = $points % self::POINTS_PER_VORI;
        $ana = intdiv($rest, self::POINTS_PER_ANA);
        $rest %= self::POINTS_PER_ANA;
        $roti = intdiv($rest, self::POINTS_PER_ROTI);
        $point = $rest % self::POINTS_PER_ROTI;

        return ['vori' => $vori, 'ana' => $ana, 'roti' => $roti, 'point' => $point];
    }

    private static function fromTraditionalUnit(string|float|int $value, int $pointsPerUnit, string $unit): int
    {
        $normalized = (string) $value;

        if (! is_numeric($normalized) || (float) $normalized < 0) {
            throw new InvalidArgumentException("Weight in {$unit} cannot be negative or non-numeric.");
        }

        // Round half up via grams-exact math: units/… * pointsPerUnit are integers per whole unit,
        // fractional units scale linearly, so multiply then round.
        return (int) bcdiv(bcadd(bcmul($normalized, (string) $pointsPerUnit, 10), '0.5', 10), '1', 0);
    }
}

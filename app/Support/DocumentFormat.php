<?php

namespace App\Support;

/**
 * Formats money and weight for printable documents.
 *
 * The invoice must show the shop's configured weight unit, so grams are
 * converted through {@see Weight} rather than printed raw. Money is always
 * grouped with thousands separators and two decimals, matching the printed cash
 * memo the counter staff already use.
 */
final class DocumentFormat
{
    public static function money(float|string|null $amount, string $currencySymbol = '৳'): string
    {
        return $currencySymbol.number_format((float) $amount, 2, '.', ',');
    }

    /**
     * @return array{value: string, unit: string}
     */
    public static function weight(float|string|null $grams, string $weightUnit = 'gram'): array
    {
        $value = (float) $grams;

        if ($weightUnit === 'vori') {
            return [
                'value' => number_format(Weight::gramsToVori($value), 4, '.', ''),
                'unit' => 'vori',
            ];
        }

        return [
            'value' => number_format($value, 3, '.', ''),
            'unit' => 'gram',
        ];
    }

    public static function weightUnitLabel(string $weightUnit): string
    {
        return $weightUnit === 'vori' ? 'ভরি' : 'গ্রাম';
    }

    /**
     * A compact Bangla amount in words for the "কথায়" line on a printed memo.
     */
    public static function amountInWords(float|string|null $amount): string
    {
        $taka = (int) floor((float) $amount);
        $paisha = (int) round(((float) $amount - $taka) * 100);

        if ($taka <= 0 && $paisha <= 0) {
            return 'শূন্য টাকা মাত্র';
        }

        $words = self::takaInWords($taka);
        $words .= ' টাকা';

        if ($paisha > 0) {
            $words .= ' '.self::paishaInWords($paisha).' পয়সা';
        }

        return $words.' মাত্র';
    }

    private static function takaInWords(int $number): string
    {
        if ($number === 0) {
            return 'শূন্য';
        }

        $scales = [
            10000000 => 'কোটি',
            100000 => 'লক্ষ',
            1000 => 'হাজার',
        ];

        $words = [];
        $remaining = $number;

        foreach ($scales as $value => $label) {
            $count = intdiv($remaining, $value);

            if ($count > 0) {
                $words[] = self::belowThousand($count).' '.$label;
                $remaining %= $value;
            }
        }

        if ($remaining > 0) {
            $words[] = self::belowThousand($remaining);
        }

        return implode(' ', $words);
    }

    /**
     * Bangla words for a number under one thousand.
     */
    private static function belowThousand(int $number): string
    {
        $digits = [
            1 => 'এক', 2 => 'দুই', 3 => 'তিন', 4 => 'চার', 5 => 'পাঁচ',
            6 => 'ছয়', 7 => 'সাত', 8 => 'আট', 9 => 'নয়',
        ];

        if ($number < 10) {
            return $digits[$number];
        }

        if ($number < 100) {
            $tens = [
                10 => 'দশ', 20 => 'বিশ', 30 => 'ত্রিশ', 40 => 'চল্লিশ', 50 => 'পঞ্চাশ',
                60 => 'ষাট', 70 => 'সত্তর', 80 => 'আশি', 90 => 'নব্বই',
            ];

            $tensValue = intdiv($number, 10) * 10;
            $unitValue = $number % 10;
            $words = $tens[$tensValue];

            return $unitValue > 0 ? $words.' '.$digits[$unitValue] : $words;
        }

        $hundredsValue = intdiv($number, 100) * 100;
        $remainder = $number % 100;

        $words = $digits[intdiv($hundredsValue, 100)].' শত';

        return $remainder > 0 ? $words.' '.self::belowThousand($remainder) : $words;
    }

    private static function paishaInWords(int $paisha): string
    {
        $exact = [
            1 => 'এক', 5 => 'পাঁচ', 10 => 'দশ', 20 => 'বিশ', 25 => 'পঁচিশ',
            50 => 'পঞ্চাশ', 75 => 'পঁচাত্তর',
        ];

        return $exact[$paisha] ?? self::belowThousand($paisha);
    }
}

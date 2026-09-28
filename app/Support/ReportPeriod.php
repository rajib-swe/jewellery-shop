<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * The reporting window shared by every report endpoint.
 *
 * A report is nearly always opened without filters, so an absent range means
 * "the last 30 days" rather than "all of time" or "nothing". Reversed bounds
 * are corrected rather than rejected, because a swapped date range in a date
 * picker is a common slip and swapping it back is what the user meant.
 *
 * The bounds come back keyed as well as positional so a call site reads
 * `$period['from']` instead of depending on the order of a list.
 */
final class ReportPeriod
{
    public const DEFAULT_DAYS = 30;

    /**
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function from(Request $request): array
    {
        $requestedTo = self::date($request->query('to')) ?? CarbonImmutable::today();
        $requestedFrom = self::date($request->query('from')) ?? $requestedTo->subDays(self::DEFAULT_DAYS - 1);

        // Both bounds are corrected together. Swapping them rather than letting
        // the report read as a single day keeps a mistyped range honest.
        return [
            'from' => $requestedFrom->min($requestedTo),
            'to' => $requestedFrom->max($requestedTo),
        ];
    }

    private static function date(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return CarbonImmutable::parse($value)->startOfDay();
    }
}

<?php

namespace App;

enum PawnPartialMonthRule: string
{
    /**
     * A part month is charged proportionally to the days it covers.
     */
    case DailyProration = 'daily_proration';

    /**
     * Any started month is charged as a full month.
     */
    case RoundUpFullMonth = 'round_up_full_month';
}

<?php

namespace App;

enum Karat: int
{
    case Eighteen = 18;
    case TwentyOne = 21;
    case TwentyTwo = 22;
    case TwentyFour = 24;

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

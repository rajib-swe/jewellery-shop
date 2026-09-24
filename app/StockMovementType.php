<?php

namespace App;

enum StockMovementType: string
{
    case In = 'in';
    case Out = 'out';
    case Adjust = 'adjust';
}

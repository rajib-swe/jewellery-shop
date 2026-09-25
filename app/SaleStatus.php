<?php

namespace App;

enum SaleStatus: string
{
    case Completed = 'completed';
    case Void = 'void';
}

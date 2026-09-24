<?php

namespace App;

enum ItemStatus: string
{
    case InStock = 'in_stock';
    case Sold = 'sold';
    case Pawned = 'pawned';
    case Scrap = 'scrap';
}

<?php

namespace App;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Bkash = 'bkash';
    case Nagad = 'nagad';
    case Card = 'card';
    case Bank = 'bank';
}

<?php

namespace App;

enum PawnPaymentType: string
{
    case Interest = 'interest';
    case Principal = 'principal';
    case Redeem = 'redeem';
}

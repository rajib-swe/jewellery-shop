<?php

namespace App;

enum PawnStatus: string
{
    case Active = 'active';
    case Redeemed = 'redeemed';
    case Forfeited = 'forfeited';
}

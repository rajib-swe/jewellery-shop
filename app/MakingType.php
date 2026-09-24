<?php

namespace App;

enum MakingType: string
{
    case PerGram = 'per_gram';
    case Fixed = 'fixed';
    case Percent = 'percent';
}

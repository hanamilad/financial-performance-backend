<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

enum ActivityType: string
{
    use HasValues;

    case Restaurant = 'RESTAURANT';
    case Cafe = 'CAFE';
}

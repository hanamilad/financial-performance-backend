<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

enum PlatformCode: string
{
    use HasValues;

    case HungerStation = 'HUNGERSTATION';
    case Jaez = 'JAEZ';
    case Keeta = 'KEETA';
    case ToYou = 'TOYOU';
    case Mrsool = 'MRSOOL';
    case TheChefz = 'THE_CHEFZ';
    case Ninja = 'NINJA';
    case Other = 'OTHER';
}

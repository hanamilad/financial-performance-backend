<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

enum OperatingStatus: string
{
    use HasValues;

    case Open = 'OPEN';
    case Closed = 'CLOSED';
}

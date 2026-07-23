<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

enum ScopeCode: string
{
    use HasValues;

    case HeadOffice = 'HEAD_OFFICE';
    case Client = 'CLIENT';
}

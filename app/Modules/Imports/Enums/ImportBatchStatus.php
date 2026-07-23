<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

enum ImportBatchStatus: string
{
    use HasValues;

    case Uploaded = 'uploaded';
    case ValidationFailed = 'validation_failed';
    case Validated = 'validated';
    case Draft = 'draft';
}

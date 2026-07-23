<?php

namespace App\Modules\Imports\Enums;

enum ImportBatchStatus: string
{
    case Uploaded = 'uploaded';
    case ValidationFailed = 'validation_failed';
    case Validated = 'validated';
    case Draft = 'draft';
}

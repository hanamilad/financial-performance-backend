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
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Published = 'published';

    public function isDeletable(): bool
    {
        return in_array($this, [self::ValidationFailed, self::Draft], true);
    }

    public function blocksReupload(): bool
    {
        return in_array($this, [
            self::Draft,
            self::Validated,
            self::UnderReview,
            self::Approved,
            self::Published,
        ], true);
    }
}

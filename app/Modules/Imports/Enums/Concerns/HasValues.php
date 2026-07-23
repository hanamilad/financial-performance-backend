<?php

namespace App\Modules\Imports\Enums\Concerns;

trait HasValues
{
    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

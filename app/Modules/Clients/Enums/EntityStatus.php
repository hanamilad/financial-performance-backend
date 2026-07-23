<?php

namespace App\Modules\Clients\Enums;

enum EntityStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

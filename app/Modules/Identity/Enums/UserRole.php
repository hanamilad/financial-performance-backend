<?php

namespace App\Modules\Identity\Enums;

enum UserRole: string
{
    case SystemAdmin = 'system_admin';
    case ClientUser = 'client_user';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}

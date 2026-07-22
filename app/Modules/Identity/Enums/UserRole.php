<?php

namespace App\Modules\Identity\Enums;

/**
 * The two roles the MVP recognises (Roadmap Phase 2, AUTH-001).
 *
 * A single `users` table carries every account and this enum is stored in its
 * indexed `role` column, so no second table or authentication guard is needed.
 * Client-scoped roles and finer permissions are out of scope for this slice.
 */
enum UserRole: string
{
    case SystemAdmin = 'system_admin';
    case ClientUser = 'client_user';
}

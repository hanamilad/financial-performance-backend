<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

/**
 * Non-branch scopes accepted in scope_code columns. A scope_code may otherwise
 * be a branch code (EXPENSES_MONTHLY, OTHER_INCOME_EXPENSES); the client-level
 * sheets (FINANCIAL_POSITION, LIQUIDITY) accept only these values.
 */
enum ScopeCode: string
{
    use HasValues;

    case HeadOffice = 'HEAD_OFFICE';
    case Client = 'CLIENT';
}

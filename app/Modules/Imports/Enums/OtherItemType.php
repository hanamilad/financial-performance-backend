<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

enum OtherItemType: string
{
    use HasValues;

    case OtherOperatingIncome = 'OTHER_OPERATING_INCOME';
    case NonOperatingIncome = 'NON_OPERATING_INCOME';
    case NonOperatingExpense = 'NON_OPERATING_EXPENSE';
    case FinanceCost = 'FINANCE_COST';
    case ZakatOrIncomeTax = 'ZAKAT_OR_INCOME_TAX';
}

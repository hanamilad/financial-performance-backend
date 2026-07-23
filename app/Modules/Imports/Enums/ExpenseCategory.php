<?php

namespace App\Modules\Imports\Enums;

use App\Modules\Imports\Enums\Concerns\HasValues;

enum ExpenseCategory: string
{
    use HasValues;

    case FoodCost = 'FOOD_COST';
    case OtherDirectCost = 'OTHER_DIRECT_COST';
    case Packaging = 'PACKAGING';
    case PlatformCommission = 'PLATFORM_COMMISSION';
    case PlatformOtherFees = 'PLATFORM_OTHER_FEES';
    case PlatformAds = 'PLATFORM_ADS';
    case Payroll = 'PAYROLL';
    case Rent = 'RENT';
    case Electricity = 'ELECTRICITY';
    case Water = 'WATER';
    case Gas = 'GAS';
    case Marketing = 'MARKETING';
    case Maintenance = 'MAINTENANCE';
    case Cleaning = 'CLEANING';
    case Transportation = 'TRANSPORTATION';
    case SoftwareSubscriptions = 'SOFTWARE_SUBSCRIPTIONS';
    case GovernmentFees = 'GOVERNMENT_FEES';
    case BankFees = 'BANK_FEES';
    case Depreciation = 'DEPRECIATION';
    case BranchAdmin = 'BRANCH_ADMIN';
    case HeadOfficeAdmin = 'HEAD_OFFICE_ADMIN';
    case OtherOperating = 'OTHER_OPERATING';
}

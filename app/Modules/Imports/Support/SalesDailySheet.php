<?php

namespace App\Modules\Imports\Support;

/**
 * The SALES_DAILY sheet is the import contract for IMPORT-001: one row per
 * branch per day. Column keys and rules come from the approved template
 * (restaurant_performance_template_v1.xlsx), not from guesswork.
 */
final class SalesDailySheet
{
    public const NAME = 'SALES_DAILY';

    public const REQUIRED_COLUMNS = [
        'date',
        'branch_code',
        'gross_sales_ex_vat',
        'discounts',
        'returns',
        'net_sales_ex_vat',
        'vat_amount',
        'order_count',
        'dine_in_sales',
        'pickup_sales',
        'direct_delivery_sales',
        'platform_sales',
        'operating_status',
    ];

    public const OPTIONAL_COLUMNS = ['note'];

    public const DECIMAL_COLUMNS = [
        'gross_sales_ex_vat',
        'discounts',
        'returns',
        'net_sales_ex_vat',
        'vat_amount',
        'dine_in_sales',
        'pickup_sales',
        'direct_delivery_sales',
        'platform_sales',
    ];

    public const OPERATING_STATUSES = ['OPEN', 'CLOSED'];
}

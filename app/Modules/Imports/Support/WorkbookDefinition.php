<?php

namespace App\Modules\Imports\Support;

use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Imports\Enums\ActivityType;
use App\Modules\Imports\Enums\ExpenseCategory;
use App\Modules\Imports\Enums\OperatingStatus;
use App\Modules\Imports\Enums\OtherItemType;
use App\Modules\Imports\Enums\PlatformCode;

/**
 * The import contract for the approved workbook
 * (restaurant_performance_template_v1.xlsx). Every column, its type, and its
 * required/optional flag are taken from the template's machine-header row and
 * its cell colouring (yellow = required, white = optional, grey = calculated),
 * not from guesswork. README/LISTS/EXAMPLES/VALIDATION_CHECKS are helper sheets
 * and are intentionally absent here, so they are never read or stored.
 */
final class WorkbookDefinition
{
    public const BRANCHES = 'BRANCHES';

    public const SALES_DAILY = 'SALES_DAILY';

    public const EXPENSES_MONTHLY = 'EXPENSES_MONTHLY';

    public const OTHER_INCOME_EXPENSES = 'OTHER_INCOME_EXPENSES';

    public const DELIVERY_PLATFORMS = 'DELIVERY_PLATFORMS';

    public const FINANCIAL_POSITION = 'FINANCIAL_POSITION';

    public const LIQUIDITY = 'LIQUIDITY';

    public const ITEMS_MONTHLY = 'ITEMS_MONTHLY';

    public const TARGETS_MONTHLY = 'TARGETS_MONTHLY';

    /**
     * @return list<SheetDefinition>
     */
    public static function sheets(): array
    {
        return [
            self::branches(),
            self::salesDaily(),
            self::expensesMonthly(),
            self::otherIncomeExpenses(),
            self::deliveryPlatforms(),
            self::financialPosition(),
            self::liquidity(),
            self::itemsMonthly(),
            self::targetsMonthly(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function sheetNames(): array
    {
        return array_map(fn (SheetDefinition $sheet) => $sheet->name, self::sheets());
    }

    private static function branches(): SheetDefinition
    {
        $statuses = array_map(
            fn (EntityStatus $status) => strtoupper($status->value),
            EntityStatus::cases(),
        );

        return new SheetDefinition(self::BRANCHES, [
            new ColumnSpec('branch_code', ColumnKind::BranchDefinitionCode),
            new ColumnSpec('branch_name', ColumnKind::Text),
            new ColumnSpec('city', ColumnKind::Text),
            new ColumnSpec('activity_type', ColumnKind::EnumValue, allowed: ActivityType::values()),
            new ColumnSpec('status', ColumnKind::EnumValue, allowed: $statuses),
        ]);
    }

    private static function salesDaily(): SheetDefinition
    {
        return new SheetDefinition(self::SALES_DAILY, [
            new ColumnSpec('date', ColumnKind::DateWithinPeriod),
            new ColumnSpec('branch_code', ColumnKind::SelectedBranchCode),
            ...self::decimals([
                'gross_sales_ex_vat', 'discounts', 'returns', 'net_sales_ex_vat', 'vat_amount',
            ]),
            new ColumnSpec('order_count', ColumnKind::IntegerUnsigned),
            ...self::decimals([
                'dine_in_sales', 'pickup_sales', 'direct_delivery_sales', 'platform_sales',
            ]),
            new ColumnSpec('operating_status', ColumnKind::EnumValue, allowed: OperatingStatus::values()),
            new ColumnSpec('note', ColumnKind::Text, required: false),
        ]);
    }

    private static function expensesMonthly(): SheetDefinition
    {
        return new SheetDefinition(self::EXPENSES_MONTHLY, [
            new ColumnSpec('month', ColumnKind::Month),
            new ColumnSpec('scope_code', ColumnKind::ScopeBranchOrScope),
            new ColumnSpec('expense_code', ColumnKind::Text),
            new ColumnSpec('expense_name', ColumnKind::Text),
            new ColumnSpec('expense_category', ColumnKind::EnumValue, allowed: ExpenseCategory::values()),
            new ColumnSpec('amount', ColumnKind::DecimalUnsigned),
            new ColumnSpec('note', ColumnKind::Text, required: false),
        ]);
    }

    private static function otherIncomeExpenses(): SheetDefinition
    {
        return new SheetDefinition(self::OTHER_INCOME_EXPENSES, [
            new ColumnSpec('month', ColumnKind::Month),
            new ColumnSpec('scope_code', ColumnKind::ScopeBranchOrScope),
            new ColumnSpec('item_code', ColumnKind::Text),
            new ColumnSpec('item_name', ColumnKind::Text),
            new ColumnSpec('item_type', ColumnKind::EnumValue, allowed: OtherItemType::values()),
            new ColumnSpec('amount', ColumnKind::DecimalUnsigned),
            new ColumnSpec('note', ColumnKind::Text, required: false),
        ]);
    }

    private static function deliveryPlatforms(): SheetDefinition
    {
        return new SheetDefinition(self::DELIVERY_PLATFORMS, [
            new ColumnSpec('month', ColumnKind::Month),
            new ColumnSpec('branch_code', ColumnKind::ClientBranchCode),
            new ColumnSpec('platform_code', ColumnKind::EnumValue, allowed: PlatformCode::values()),
            new ColumnSpec('platform_name', ColumnKind::Text),
            ...self::decimals(['gross_sales_ex_vat', 'vat_amount', 'restaurant_funded_discounts']),
            new ColumnSpec('platform_funded_discounts', ColumnKind::DecimalUnsigned, required: false),
            new ColumnSpec('returns', ColumnKind::DecimalUnsigned),
            new ColumnSpec('order_count', ColumnKind::IntegerUnsigned),
            ...self::decimals(['commission', 'additional_fees', 'commission_vat', 'platform_ads']),
            new ColumnSpec('net_settlement', ColumnKind::DecimalUnsigned, required: false),
            new ColumnSpec('note', ColumnKind::Text, required: false),
        ]);
    }

    private static function financialPosition(): SheetDefinition
    {
        return new SheetDefinition(self::FINANCIAL_POSITION, [
            new ColumnSpec('report_date', ColumnKind::DateWithinPeriod),
            new ColumnSpec('scope_code', ColumnKind::ScopeOnly),
            // Balance-sheet lines may be negative (contra-asset accumulated
            // depreciation, drawings, accumulated losses), so they are signed.
            ...self::signedDecimals([
                'cash_on_hand', 'bank_balances', 'wallet_balances', 'platform_receivables',
                'trade_receivables', 'inventory', 'prepaid_expenses', 'other_current_assets',
                'equipment', 'furniture', 'vehicles', 'leasehold_improvements', 'other_fixed_assets',
                'accumulated_depreciation', 'suppliers', 'accrued_expenses', 'payroll_payable',
                'vat_payable', 'tax_or_zakat_payable', 'short_term_loans', 'other_current_liabilities',
                'long_term_loans', 'lease_liabilities', 'other_non_current_liabilities', 'capital',
                'partner_accounts', 'retained_earnings', 'current_period_profit',
                'drawings_or_distributions', 'other_equity',
            ]),
        ]);
    }

    private static function liquidity(): SheetDefinition
    {
        return new SheetDefinition(self::LIQUIDITY, [
            new ColumnSpec('report_date', ColumnKind::DateWithinPeriod),
            new ColumnSpec('scope_code', ColumnKind::ScopeOnly),
            ...self::decimals([
                'cash_on_hand', 'bank_balances', 'wallet_balances', 'platform_receivables_expected',
                'other_expected_collections', 'suppliers_due', 'payroll_due', 'taxes_due',
                'loan_installments_due', 'lease_due', 'other_near_term_obligations',
            ]),
            new ColumnSpec('obligation_horizon_days', ColumnKind::IntegerUnsigned),
        ]);
    }

    private static function itemsMonthly(): SheetDefinition
    {
        return new SheetDefinition(self::ITEMS_MONTHLY, [
            new ColumnSpec('month', ColumnKind::Month),
            new ColumnSpec('branch_code', ColumnKind::ClientBranchCode),
            new ColumnSpec('item_code', ColumnKind::Text),
            new ColumnSpec('item_name', ColumnKind::Text),
            new ColumnSpec('category_code', ColumnKind::Text),
            new ColumnSpec('category_name', ColumnKind::Text),
            ...self::decimals([
                'quantity_sold', 'gross_sales_ex_vat', 'discounts', 'returns', 'net_sales_ex_vat', 'item_cost',
            ]),
            new ColumnSpec('order_occurrences', ColumnKind::IntegerUnsigned, required: false),
        ]);
    }

    private static function targetsMonthly(): SheetDefinition
    {
        return new SheetDefinition(self::TARGETS_MONTHLY, [
            new ColumnSpec('month', ColumnKind::Month),
            new ColumnSpec('branch_code', ColumnKind::ClientBranchCode),
            new ColumnSpec('sales_target', ColumnKind::DecimalUnsigned),
            new ColumnSpec('order_count_target', ColumnKind::IntegerUnsigned, required: false),
            ...self::decimals([
                'average_order_value_target', 'food_cost_percentage_target', 'packaging_percentage_target',
                'payroll_percentage_target', 'gross_profit_target', 'net_profit_target', 'net_profit_margin_target',
            ], required: false),
        ]);
    }

    /**
     * @param  list<string>  $names
     * @return list<ColumnSpec>
     */
    private static function decimals(array $names, bool $required = true): array
    {
        return array_map(
            fn (string $name) => new ColumnSpec($name, ColumnKind::DecimalUnsigned, required: $required),
            $names,
        );
    }

    /**
     * @param  list<string>  $names
     * @return list<ColumnSpec>
     */
    private static function signedDecimals(array $names): array
    {
        return array_map(
            fn (string $name) => new ColumnSpec($name, ColumnKind::DecimalSigned),
            $names,
        );
    }
}

<?php

use App\Models\User;
use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

uses(RefreshDatabase::class);

function sheetHeaders(): array
{
    return [
        'BRANCHES' => ['branch_code', 'branch_name', 'city', 'activity_type', 'status'],
        'SALES_DAILY' => [
            'date', 'branch_code', 'gross_sales_ex_vat', 'discounts', 'returns', 'net_sales_ex_vat',
            'vat_amount', 'order_count', 'dine_in_sales', 'pickup_sales', 'direct_delivery_sales',
            'platform_sales', 'operating_status', 'note',
        ],
        'EXPENSES_MONTHLY' => ['month', 'scope_code', 'expense_code', 'expense_name', 'expense_category', 'amount', 'note'],
        'OTHER_INCOME_EXPENSES' => ['month', 'scope_code', 'item_code', 'item_name', 'item_type', 'amount', 'note'],
        'DELIVERY_PLATFORMS' => [
            'month', 'branch_code', 'platform_code', 'platform_name', 'gross_sales_ex_vat', 'vat_amount',
            'restaurant_funded_discounts', 'platform_funded_discounts', 'returns', 'order_count', 'commission',
            'additional_fees', 'commission_vat', 'platform_ads', 'net_settlement', 'note',
        ],
        'FINANCIAL_POSITION' => [
            'report_date', 'scope_code', 'cash_on_hand', 'bank_balances', 'wallet_balances', 'platform_receivables',
            'trade_receivables', 'inventory', 'prepaid_expenses', 'other_current_assets', 'equipment', 'furniture',
            'vehicles', 'leasehold_improvements', 'other_fixed_assets', 'accumulated_depreciation', 'suppliers',
            'accrued_expenses', 'payroll_payable', 'vat_payable', 'tax_or_zakat_payable', 'short_term_loans',
            'other_current_liabilities', 'long_term_loans', 'lease_liabilities', 'other_non_current_liabilities',
            'capital', 'partner_accounts', 'retained_earnings', 'current_period_profit', 'drawings_or_distributions',
            'other_equity',
        ],
        'LIQUIDITY' => [
            'report_date', 'scope_code', 'cash_on_hand', 'bank_balances', 'wallet_balances',
            'platform_receivables_expected', 'other_expected_collections', 'suppliers_due', 'payroll_due',
            'taxes_due', 'loan_installments_due', 'lease_due', 'other_near_term_obligations', 'obligation_horizon_days',
        ],
        'ITEMS_MONTHLY' => [
            'month', 'branch_code', 'item_code', 'item_name', 'category_code', 'category_name', 'quantity_sold',
            'gross_sales_ex_vat', 'discounts', 'returns', 'net_sales_ex_vat', 'item_cost', 'order_occurrences',
        ],
        'TARGETS_MONTHLY' => [
            'month', 'branch_code', 'sales_target', 'order_count_target', 'average_order_value_target',
            'food_cost_percentage_target', 'packaging_percentage_target', 'payroll_percentage_target',
            'gross_profit_target', 'net_profit_target', 'net_profit_margin_target',
        ],
    ];
}

function defaultRow(string $branchCode): array
{
    $financial = ['report_date' => '2026-01-31', 'scope_code' => 'CLIENT'];
    foreach (array_slice(sheetHeaders()['FINANCIAL_POSITION'], 2) as $column) {
        $financial[$column] = $column === 'accumulated_depreciation' ? '-5000' : '1000';
    }

    return [
        'BRANCHES' => ['branch_code' => $branchCode, 'branch_name' => 'الفرع', 'city' => 'الرياض', 'activity_type' => 'RESTAURANT', 'status' => 'ACTIVE'],
        'SALES_DAILY' => [
            'date' => '2026-01-03', 'branch_code' => $branchCode, 'gross_sales_ex_vat' => '1000', 'discounts' => '50',
            'returns' => '20', 'net_sales_ex_vat' => '930', 'vat_amount' => '139.5', 'order_count' => '80',
            'dine_in_sales' => '600', 'pickup_sales' => '200', 'direct_delivery_sales' => '100', 'platform_sales' => '30',
            'operating_status' => 'OPEN', 'note' => '',
        ],
        'EXPENSES_MONTHLY' => ['month' => '2026-01', 'scope_code' => $branchCode, 'expense_code' => 'FC-001', 'expense_name' => 'مواد', 'expense_category' => 'FOOD_COST', 'amount' => '34000', 'note' => ''],
        'OTHER_INCOME_EXPENSES' => ['month' => '2026-01', 'scope_code' => 'HEAD_OFFICE', 'item_code' => 'OI-001', 'item_name' => 'إيراد', 'item_type' => 'OTHER_OPERATING_INCOME', 'amount' => '500', 'note' => ''],
        'DELIVERY_PLATFORMS' => [
            'month' => '2026-01', 'branch_code' => $branchCode, 'platform_code' => 'HUNGERSTATION', 'platform_name' => 'هنقرستيشن',
            'gross_sales_ex_vat' => '42000', 'vat_amount' => '6300', 'restaurant_funded_discounts' => '100',
            'platform_funded_discounts' => '50', 'returns' => '20', 'order_count' => '300', 'commission' => '6300',
            'additional_fees' => '100', 'commission_vat' => '945', 'platform_ads' => '200', 'net_settlement' => '34000', 'note' => '',
        ],
        'FINANCIAL_POSITION' => $financial,
        'LIQUIDITY' => [
            'report_date' => '2026-01-31', 'scope_code' => 'CLIENT', 'cash_on_hand' => '15000', 'bank_balances' => '120000',
            'wallet_balances' => '0', 'platform_receivables_expected' => '45000', 'other_expected_collections' => '0',
            'suppliers_due' => '70000', 'payroll_due' => '0', 'taxes_due' => '0', 'loan_installments_due' => '0',
            'lease_due' => '0', 'other_near_term_obligations' => '0', 'obligation_horizon_days' => '30',
        ],
        'ITEMS_MONTHLY' => [
            'month' => '2026-01', 'branch_code' => $branchCode, 'item_code' => 'ITM-001', 'item_name' => 'برجر',
            'category_code' => 'CAT-1', 'category_name' => 'وجبات', 'quantity_sold' => '1250', 'gross_sales_ex_vat' => '47500',
            'discounts' => '0', 'returns' => '0', 'net_sales_ex_vat' => '47500', 'item_cost' => '19000', 'order_occurrences' => '',
        ],
        'TARGETS_MONTHLY' => [
            'month' => '2026-01', 'branch_code' => $branchCode, 'sales_target' => '100000', 'order_count_target' => '',
            'average_order_value_target' => '', 'food_cost_percentage_target' => '', 'packaging_percentage_target' => '',
            'payroll_percentage_target' => '', 'gross_profit_target' => '', 'net_profit_target' => '', 'net_profit_margin_target' => '',
        ],
    ];
}

function performanceWorkbook(string $branchCode, array $options = []): UploadedFile
{
    $omit = $options['omit'] ?? [];
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach (sheetHeaders() as $name => $defaultHeaders) {
        if (in_array($name, $omit, true)) {
            continue;
        }

        $headers = $options['headers'][$name] ?? $defaultHeaders;
        $rows = $options['rows'][$name] ?? [defaultRow($branchCode)[$name]];
        $rows = [...$rows, ...($options['extraRows'][$name] ?? [])];

        $worksheet = $spreadsheet->createSheet();
        $worksheet->setTitle($name);
        $worksheet->fromArray($headers, null, 'A1');

        foreach ($rows as $r => $row) {
            $ordered = array_map(fn (string $key) => $row[$key] ?? '', $headers);
            $worksheet->fromArray($ordered, null, 'A'.($r + 2));
        }
    }

    $path = tempnam(sys_get_temp_dir(), 'import').'.xlsx';
    (new XlsxWriter($spreadsheet))->save($path);

    return new UploadedFile(
        $path,
        'performance.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

function branchForImport(string $code = 'BR-1'): Branch
{
    return Branch::factory()->for(Client::factory())->create(['code' => $code]);
}

function uploadWorkbook(Branch $branch, array $options = [], string $period = '2026-01'): array
{
    return [
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => $period,
        'file' => performanceWorkbook($branch->code, $options),
    ];
}

it('accepts a complete workbook and stores every sheet as a draft', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch))
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.error_count', 0);

    expect($response->json('data.row_count'))->toBe(9);

    $batch = ImportBatch::sole();
    $sheets = $batch->rows()->pluck('sheet_name')->unique()->sort()->values()->all();
    expect($sheets)->toBe([
        'BRANCHES', 'DELIVERY_PLATFORMS', 'EXPENSES_MONTHLY', 'FINANCIAL_POSITION', 'ITEMS_MONTHLY',
        'LIQUIDITY', 'OTHER_INCOME_EXPENSES', 'SALES_DAILY', 'TARGETS_MONTHLY',
    ]);
});

it('stores each row under its own sheet name and row number', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $this->post('/api/v1/admin/imports', uploadWorkbook($branch))->assertCreated();

    $sales = ImportBatch::sole()->rows()->where('sheet_name', 'SALES_DAILY')->sole();
    expect($sales->row_number)->toBe(2)
        ->and($sales->data['branch_code'])->toBe($branch->code)
        ->and($sales->data['operating_status'])->toBe('OPEN');
});

it('fails the whole import when a required sheet is missing', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch, ['omit' => ['TARGETS_MONTHLY']]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'validation_failed');

    expect(collect($response->json('data.errors'))->firstWhere('sheet', 'TARGETS_MONTHLY'))->not->toBeNull()
        ->and(ImportBatch::sole()->rows()->count())->toBe(0);
});

it('reports missing required columns in a single sheet with the sheet name', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $headers = array_values(array_diff(sheetHeaders()['EXPENSES_MONTHLY'], ['expense_category']));

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch, ['headers' => ['EXPENSES_MONTHLY' => $headers]]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'validation_failed');

    $error = collect($response->json('data.errors'))->firstWhere('column', 'expense_category');
    expect($error)->not->toBeNull()
        ->and($error['sheet'])->toBe('EXPENSES_MONTHLY')
        ->and(ImportBatch::sole()->rows()->count())->toBe(0);
});

it('lets one bad row in one sheet block the entire draft', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $badItem = ['month' => '2026-01', 'branch_code' => $branch->code, 'item_code' => 'ITM-9', 'item_name' => 'x',
        'category_code' => 'C', 'category_name' => 'ص', 'quantity_sold' => '-3', 'gross_sales_ex_vat' => '10',
        'discounts' => '0', 'returns' => '0', 'net_sales_ex_vat' => '10', 'item_cost' => '4', 'order_occurrences' => ''];

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch, ['extraRows' => ['ITEMS_MONTHLY' => [$badItem]]]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'validation_failed');

    $error = collect($response->json('data.errors'))->firstWhere('column', 'quantity_sold');
    expect($error['sheet'])->toBe('ITEMS_MONTHLY')
        ->and(ImportBatch::sole()->rows()->count())->toBe(0);
});

it('rejects a branch_code that does not belong to the client', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $rows = ['DELIVERY_PLATFORMS' => [array_merge(defaultRow($branch->code)['DELIVERY_PLATFORMS'], ['branch_code' => 'FOREIGN-1'])]];

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch, ['rows' => $rows]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'validation_failed');

    $error = collect($response->json('data.errors'))->firstWhere('sheet', 'DELIVERY_PLATFORMS');
    expect($error['column'])->toBe('branch_code')
        ->and($error['reason'])->toContain('العميل')
        ->and(ImportBatch::sole()->rows()->count())->toBe(0);
});

it('flags a cross-sheet branch not declared in the BRANCHES sheet', function () {
    actingAsSystemAdmin();
    $client = Client::factory()->create();
    $branch = Branch::factory()->for($client)->create(['code' => 'BR-1']);
    Branch::factory()->for($client)->create(['code' => 'BR-2']);

    $rows = ['TARGETS_MONTHLY' => [array_merge(defaultRow('BR-1')['TARGETS_MONTHLY'], ['branch_code' => 'BR-2'])]];

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch, ['rows' => $rows]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'validation_failed');

    $error = collect($response->json('data.errors'))
        ->first(fn ($e) => $e['sheet'] === 'TARGETS_MONTHLY' && $e['value'] === 'BR-2');
    expect($error['reason'])->toContain('BRANCHES')
        ->and(ImportBatch::sole()->rows()->count())->toBe(0);
});

it('keeps the SALES_DAILY branch scoped to the selected branch', function () {
    actingAsSystemAdmin();
    $client = Client::factory()->create();
    $branch = Branch::factory()->for($client)->create(['code' => 'BR-1']);
    Branch::factory()->for($client)->create(['code' => 'BR-2']);

    $rows = [
        'BRANCHES' => [defaultRow('BR-1')['BRANCHES'], defaultRow('BR-2')['BRANCHES']],
        'SALES_DAILY' => [array_merge(defaultRow('BR-1')['SALES_DAILY'], ['branch_code' => 'BR-2'])],
    ];

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch, ['rows' => $rows]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'validation_failed');

    $error = collect($response->json('data.errors'))->firstWhere('sheet', 'SALES_DAILY');
    expect($error['column'])->toBe('branch_code')
        ->and($error['reason'])->toContain('المختار');
});

it('records row-level errors for invalid sales values', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $rows = ['SALES_DAILY' => [
        array_merge(defaultRow($branch->code)['SALES_DAILY'], ['gross_sales_ex_vat' => '-5']),
        array_merge(defaultRow($branch->code)['SALES_DAILY'], ['date' => '2026-02-10']),
        array_merge(defaultRow($branch->code)['SALES_DAILY'], ['operating_status' => 'PAUSED']),
    ]];

    $response = $this->post('/api/v1/admin/imports', uploadWorkbook($branch, ['rows' => $rows]))
        ->assertCreated()
        ->assertJsonPath('data.status', 'validation_failed');

    $columns = collect($response->json('data.errors'))->pluck('column');
    expect($columns)->toContain('gross_sales_ex_vat', 'date', 'operating_status')
        ->and(ImportBatch::sole()->rows()->count())->toBe(0);
});

it('rejects a non-xlsx file', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $this->post('/api/v1/admin/imports', [
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => UploadedFile::fake()->create('data.txt', 20, 'text/plain'),
    ])->assertStatus(422)->assertJsonValidationErrors('file');
});

it('rejects a branch that does not belong to the client at request level', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();
    $otherClient = Client::factory()->create();

    $this->post('/api/v1/admin/imports', [
        'client_id' => $otherClient->id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => performanceWorkbook($branch->code),
    ])->assertStatus(422)->assertJsonValidationErrors('branch_id');
});

it('returns 409 for a duplicate client, branch and period', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $this->post('/api/v1/admin/imports', uploadWorkbook($branch))->assertCreated()->assertJsonPath('data.status', 'draft');
    $this->post('/api/v1/admin/imports', uploadWorkbook($branch))->assertStatus(409);
});

it('forbids a client_user from the import APIs', function () {
    Sanctum::actingAs(User::factory()->clientUser()->create());

    $this->getJson('/api/v1/admin/imports')->assertForbidden();
});

it('requires authentication for the import APIs', function () {
    $this->getJson('/api/v1/admin/imports')->assertUnauthorized();
});

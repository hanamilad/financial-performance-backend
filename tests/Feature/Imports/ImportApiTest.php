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

const SALES_HEADERS = [
    'date', 'branch_code', 'gross_sales_ex_vat', 'discounts', 'returns',
    'net_sales_ex_vat', 'vat_amount', 'order_count', 'dine_in_sales',
    'pickup_sales', 'direct_delivery_sales', 'platform_sales', 'operating_status', 'note',
];

/**
 * @param  array<int, array<int, mixed>>  $dataRows
 * @param  array<int, string>  $headers
 */
function salesWorkbook(array $dataRows, ?array $headers = null): UploadedFile
{
    $rows = [$headers ?? SALES_HEADERS, ...$dataRows];

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->setTitle('SALES_DAILY');
    $sheet = $spreadsheet->getActiveSheet();
    foreach ($rows as $r => $cells) {
        foreach ($cells as $c => $value) {
            $sheet->setCellValue([$c + 1, $r + 1], $value);
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<int, mixed>
 */
function salesRow(string $branchCode, string $date = '2026-01-05', array $overrides = []): array
{
    $values = array_merge([
        'date' => $date,
        'branch_code' => $branchCode,
        'gross_sales_ex_vat' => '1000',
        'discounts' => '50',
        'returns' => '20',
        'net_sales_ex_vat' => '930',
        'vat_amount' => '139.5',
        'order_count' => '80',
        'dine_in_sales' => '600',
        'pickup_sales' => '200',
        'direct_delivery_sales' => '100',
        'platform_sales' => '30',
        'operating_status' => 'OPEN',
        'note' => '',
    ], $overrides);

    return array_map(fn ($key) => $values[$key], SALES_HEADERS);
}

function branchForImport(string $code = 'BR-1'): Branch
{
    return Branch::factory()->for(Client::factory())->create(['code' => $code]);
}

it('accepts a valid workbook and stores rows as a draft', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $file = salesWorkbook([
        salesRow($branch->code, '2026-01-03'),
        salesRow($branch->code, '2026-01-04'),
    ]);

    $this->post('/api/v1/admin/imports', [
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => $file,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.row_count', 2)
        ->assertJsonPath('data.error_count', 0)
        ->assertJsonCount(2, 'data.rows');

    $batch = ImportBatch::sole();
    expect($batch->rows()->count())->toBe(2);
});

it('reports missing required columns without saving a draft', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $headers = array_values(array_diff(SALES_HEADERS, ['operating_status']));
    $row = array_map(fn ($key) => salesRow($branch->code)[array_search($key, SALES_HEADERS, true)], $headers);
    $file = salesWorkbook([$row], $headers);

    $response = $this->post('/api/v1/admin/imports', [
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => $file,
    ])->assertCreated()->assertJsonPath('data.status', 'validation_failed');

    expect(collect($response->json('data.errors'))->pluck('column'))->toContain('operating_status')
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

it('rejects a branch that does not belong to the client', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();
    $otherClient = Client::factory()->create();

    $this->post('/api/v1/admin/imports', [
        'client_id' => $otherClient->id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => salesWorkbook([salesRow($branch->code)]),
    ])->assertStatus(422)->assertJsonValidationErrors('branch_id');
});

it('records row-level errors for invalid values and saves no draft', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $file = salesWorkbook([
        salesRow($branch->code, '2026-01-05', ['gross_sales_ex_vat' => '-5']),
        salesRow($branch->code, '2026-02-10'),
        salesRow($branch->code, '2026-01-06', ['operating_status' => 'PAUSED']),
        salesRow('WRONG-CODE', '2026-01-07'),
    ]);

    $response = $this->post('/api/v1/admin/imports', [
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => $file,
    ])->assertCreated()->assertJsonPath('data.status', 'validation_failed');

    expect($response->json('data.error_count'))->toBeGreaterThanOrEqual(4)
        ->and(ImportBatch::sole()->rows()->count())->toBe(0);

    $columns = collect($response->json('data.errors'))->pluck('column');
    expect($columns)->toContain('gross_sales_ex_vat', 'date', 'operating_status', 'branch_code');
});

it('returns 409 for a duplicate client, branch and period', function () {
    actingAsSystemAdmin();
    $branch = branchForImport();

    $payload = fn () => [
        'client_id' => $branch->client_id,
        'branch_id' => $branch->id,
        'reporting_period' => '2026-01',
        'file' => salesWorkbook([salesRow($branch->code)]),
    ];

    $this->post('/api/v1/admin/imports', $payload())->assertCreated()->assertJsonPath('data.status', 'draft');
    $this->post('/api/v1/admin/imports', $payload())->assertStatus(409);
});

it('forbids a client_user from the import APIs', function () {
    Sanctum::actingAs(User::factory()->clientUser()->create());

    $this->getJson('/api/v1/admin/imports')->assertForbidden();
});

it('requires authentication for the import APIs', function () {
    $this->getJson('/api/v1/admin/imports')->assertUnauthorized();
});

<?php

namespace App\Modules\Imports\Services;

use App\Modules\Clients\Models\Branch;
use App\Modules\Imports\Support\ImportValidationResult;
use App\Modules\Imports\Support\PerformanceWorkbookReader;
use App\Modules\Imports\Support\SalesDailySheet;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ValidatePerformanceImport
{
    private const MAX_ERRORS = 500;

    public function validate(UploadedFile $file, Branch $branch, string $period): ImportValidationResult
    {
        $reader = new PerformanceWorkbookReader;

        try {
            // The reader type is passed explicitly: an uploaded temp file has no
            // .xlsx extension, so maatwebsite cannot infer it and would throw.
            Excel::import($reader, $file, null, ExcelFormat::XLSX);
        } catch (Throwable) {
            // A workbook that cannot be parsed at all is reported as one error
            // rather than surfacing the library's internal exception message.
            return $this->singleError('file', 'تعذّر قراءة ملف Excel. تأكد من أنه ملف صالح.');
        }

        if (! $reader->salesDailySheetFound) {
            return $this->singleError(SalesDailySheet::NAME, 'ورقة SALES_DAILY غير موجودة في الملف.');
        }

        $rows = $reader->salesDailyRows;
        $headerIndex = $this->findHeaderRow($rows);

        if ($headerIndex === null) {
            return $this->singleError('header', 'تعذّر العثور على صف عناوين الأعمدة داخل ورقة SALES_DAILY.');
        }

        $columnIndex = $this->mapColumns($rows[$headerIndex]);
        $missing = array_values(array_diff(SalesDailySheet::REQUIRED_COLUMNS, array_keys($columnIndex)));

        if ($missing !== []) {
            $errors = [];
            foreach ($missing as $column) {
                $errors[] = ['row' => $headerIndex + 1, 'column' => $column, 'value' => null, 'reason' => 'عمود مطلوب مفقود.'];
            }

            return new ImportValidationResult([], $errors);
        }

        $validRows = [];
        $errors = [];

        for ($index = $headerIndex + 1; $index < count($rows); $index++) {
            if ($this->isEmptyRow($rows[$index], $columnIndex)) {
                continue;
            }

            $excelRow = $index + 1;
            [$data, $rowErrors] = $this->validateRow($rows[$index], $columnIndex, $branch, $period, $excelRow);

            if ($rowErrors === []) {
                $validRows[] = ['row_number' => $excelRow, 'data' => $data];

                continue;
            }

            foreach ($rowErrors as $error) {
                if (count($errors) >= self::MAX_ERRORS) {
                    break 2;
                }
                $errors[] = $error;
            }
        }

        return new ImportValidationResult($validRows, $errors);
    }

    private function singleError(string $column, string $reason): ImportValidationResult
    {
        return new ImportValidationResult([], [
            ['row' => 0, 'column' => $column, 'value' => null, 'reason' => $reason],
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function findHeaderRow(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            $values = array_map(fn ($cell) => is_string($cell) ? trim($cell) : $cell, $row);
            if (in_array('date', $values, true) && in_array('branch_code', $values, true)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return array<string, int>
     */
    private function mapColumns(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $index => $cell) {
            $key = is_string($cell) ? trim($cell) : (string) $cell;
            if ($key !== '' && ! isset($map[$key])) {
                $map[$key] = $index;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnIndex
     */
    private function isEmptyRow(array $row, array $columnIndex): bool
    {
        foreach ([...SalesDailySheet::REQUIRED_COLUMNS, ...SalesDailySheet::OPTIONAL_COLUMNS] as $column) {
            $value = $row[$columnIndex[$column] ?? -1] ?? null;
            if (is_string($value) ? trim($value) !== '' : $value !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, mixed>  $row
     * @param  array<string, int>  $columnIndex
     * @return array{0: array<string, mixed>, 1: list<array{row:int, column:string, value:mixed, reason:string}>}
     */
    private function validateRow(array $row, array $columnIndex, Branch $branch, string $period, int $excelRow): array
    {
        $data = [];
        $errors = [];
        $fail = function (string $column, mixed $value, string $reason) use (&$errors, $excelRow): void {
            $errors[] = ['row' => $excelRow, 'column' => $column, 'value' => $this->displayValue($value), 'reason' => $reason];
        };
        $cell = fn (string $column) => $row[$columnIndex[$column]] ?? null;

        $branchCode = trim((string) $cell('branch_code'));
        if ($branchCode === '') {
            $fail('branch_code', null, 'قيمة مطلوبة مفقودة.');
        } elseif ($branchCode !== $branch->code) {
            $fail('branch_code', $branchCode, 'كود الفرع لا يطابق الفرع المختار.');
        } else {
            $data['branch_code'] = $branchCode;
        }

        $date = $this->parseDate($cell('date'));
        if ($date === null) {
            $fail('date', $cell('date'), 'تاريخ غير صالح.');
        } elseif ($date->format('Y-m') !== $period) {
            $fail('date', $date->format('Y-m-d'), 'التاريخ خارج الفترة المحددة.');
        } else {
            $data['date'] = $date->format('Y-m-d');
        }

        foreach (SalesDailySheet::DECIMAL_COLUMNS as $column) {
            $value = $cell($column);
            $normalized = $this->parseDecimal($value);
            if ($normalized === null) {
                $fail($column, $value, 'قيمة رقمية غير صالحة.');
            } elseif (str_starts_with($normalized, '-')) {
                $fail($column, $value, 'قيمة سالبة غير مسموحة.');
            } else {
                $data[$column] = $normalized;
            }
        }

        $orderCount = $this->parseInteger($cell('order_count'));
        if ($orderCount === null || $orderCount < 0) {
            $fail('order_count', $cell('order_count'), 'عدد الطلبات يجب أن يكون عددًا صحيحًا غير سالب.');
        } else {
            $data['order_count'] = $orderCount;
        }

        $status = strtoupper(trim((string) $cell('operating_status')));
        if ($status === '') {
            $fail('operating_status', null, 'قيمة مطلوبة مفقودة.');
        } elseif (! in_array($status, SalesDailySheet::OPERATING_STATUSES, true)) {
            $fail('operating_status', $status, 'حالة تشغيل غير معروفة.');
        } else {
            $data['operating_status'] = $status;
        }

        $note = trim((string) $cell('note'));
        if ($note !== '') {
            $data['note'] = $note;
        }

        return [$data, $errors];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value));
            }

            return CarbonImmutable::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDecimal(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        return $trimmed;
    }

    private function parseInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        $trimmed = trim((string) $value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return null;
        }

        $float = (float) $trimmed;
        if ($float !== floor($float)) {
            return null;
        }

        return (int) $float;
    }

    private function displayValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return null;
    }
}

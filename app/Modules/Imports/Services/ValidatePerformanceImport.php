<?php

namespace App\Modules\Imports\Services;

use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use App\Modules\Imports\Enums\ScopeCode;
use App\Modules\Imports\Support\ColumnKind;
use App\Modules\Imports\Support\ColumnSpec;
use App\Modules\Imports\Support\ImportValidationResult;
use App\Modules\Imports\Support\PerformanceWorkbookReader;
use App\Modules\Imports\Support\SheetDefinition;
use App\Modules\Imports\Support\WorkbookDefinition;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

class ValidatePerformanceImport
{
    private const MAX_ERRORS = 500;

    public function validate(UploadedFile $file, Client $client, Branch $branch, string $period): ImportValidationResult
    {
        $reader = new PerformanceWorkbookReader;

        try {
            Excel::import($reader, $file, null, ExcelFormat::XLSX);
        } catch (Throwable) {
            return $this->singleError('file', 'file', 'تعذّر قراءة ملف Excel. تأكد من أنه ملف صالح.');
        }

        $branchCodeSet = array_flip($client->branches()->pluck('code')->all());

        $errors = [];
        $validRows = [];
        $branchReferences = [];
        $declaredBranchCodes = [];

        foreach (WorkbookDefinition::sheets() as $sheet) {
            if (! ($reader->sheetFound[$sheet->name] ?? false)) {
                $errors[] = $this->error($sheet->name, 0, 'sheet', null, "ورقة {$sheet->name} مطلوبة غير موجودة في الملف.");

                continue;
            }

            $rows = $reader->sheetRows[$sheet->name];
            $headerIndex = $this->findHeaderRow($rows, $sheet->anchorColumns());

            if ($headerIndex === null) {
                $errors[] = $this->error($sheet->name, 0, 'header', null, "تعذّر العثور على صف عناوين الأعمدة في ورقة {$sheet->name}.");

                continue;
            }

            $columnIndex = $this->mapColumns($rows[$headerIndex]);
            $missing = array_values(array_diff($sheet->requiredColumnNames(), array_keys($columnIndex)));

            if ($missing !== []) {
                foreach ($missing as $column) {
                    $errors[] = $this->error($sheet->name, $headerIndex + 1, $column, null, 'عمود مطلوب مفقود.');
                }

                continue;
            }

            for ($index = $headerIndex + 1; $index < count($rows); $index++) {
                if ($this->isEmptyRow($rows[$index], $columnIndex, $sheet)) {
                    continue;
                }

                $excelRow = $index + 1;
                [$data, $rowErrors, $refs] = $this->validateRow($sheet, $rows[$index], $columnIndex, $branch, $period, $excelRow, $branchCodeSet);
                array_push($branchReferences, ...$refs);

                if ($rowErrors === []) {
                    $validRows[] = ['sheet_name' => $sheet->name, 'row_number' => $excelRow, 'data' => $data];

                    if ($sheet->name === WorkbookDefinition::BRANCHES) {
                        $declaredBranchCodes[$data['branch_code']] = true;
                    }

                    continue;
                }

                array_push($errors, ...$rowErrors);
            }

            if (count($errors) >= self::MAX_ERRORS) {
                break;
            }
        }

        foreach ($this->crossSheetBranchErrors($branchReferences, $declaredBranchCodes) as $error) {
            $errors[] = $error;
        }

        if (count($errors) > self::MAX_ERRORS) {
            $errors = array_slice($errors, 0, self::MAX_ERRORS);
        }

        return new ImportValidationResult($validRows, $errors);
    }

    private function crossSheetBranchErrors(array $references, array $declaredBranchCodes): array
    {
        if ($declaredBranchCodes === []) {
            return [];
        }

        $errors = [];
        foreach ($references as $reference) {
            if (! isset($declaredBranchCodes[$reference['code']])) {
                $errors[] = $this->error(
                    $reference['sheet'], $reference['row'], $reference['column'], $reference['code'],
                    'كود الفرع غير معرّف في ورقة BRANCHES.',
                );
            }
        }

        return $errors;
    }

    private function validateRow(SheetDefinition $sheet, array $row, array $columnIndex, Branch $branch, string $period, int $excelRow, array $branchCodeSet): array
    {
        $data = [];
        $errors = [];
        $references = [];
        $cell = fn (string $column) => $row[$columnIndex[$column]] ?? null;

        foreach ($sheet->columns as $column) {
            $raw = $cell($column->name);
            $blank = is_string($raw) ? trim($raw) === '' : $raw === null;

            if ($blank) {
                if ($column->required) {
                    $errors[] = $this->error($sheet->name, $excelRow, $column->name, null, 'قيمة مطلوبة مفقودة.');
                }

                continue;
            }

            [$value, $reason, $reference] = $this->validateCell($column, $raw, $branch, $period, $branchCodeSet);

            if ($reason !== null) {
                $errors[] = $this->error($sheet->name, $excelRow, $column->name, $raw, $reason);

                continue;
            }

            $data[$column->name] = $value;

            if ($reference !== null) {
                $references[] = ['sheet' => $sheet->name, 'row' => $excelRow, 'column' => $column->name, 'code' => $reference];
            }
        }

        return [$data, $errors, $references];
    }

    private function validateCell(ColumnSpec $column, mixed $raw, Branch $branch, string $period, array $branchCodeSet): array
    {
        switch ($column->kind) {
            case ColumnKind::DateWithinPeriod:
                $date = $this->parseDate($raw);
                if ($date === null) {
                    return [null, 'تاريخ غير صالح.', null];
                }
                if ($date->format('Y-m') !== $period) {
                    return [null, 'التاريخ خارج الفترة المحددة.', null];
                }

                return [$date->format('Y-m-d'), null, null];

            case ColumnKind::Month:
                $month = $this->parseMonth($raw);
                if ($month === null) {
                    return [null, 'صيغة الشهر يجب أن تكون YYYY-MM.', null];
                }
                if ($month !== $period) {
                    return [null, 'الشهر لا يطابق فترة التقرير.', null];
                }

                return [$month, null, null];

            case ColumnKind::DecimalUnsigned:
                $number = $this->parseDecimal($raw);
                if ($number === null) {
                    return [null, 'قيمة رقمية غير صالحة.', null];
                }
                if (str_starts_with($number, '-')) {
                    return [null, 'قيمة سالبة غير مسموحة.', null];
                }

                return [$number, null, null];

            case ColumnKind::DecimalSigned:
                $number = $this->parseDecimal($raw);
                if ($number === null) {
                    return [null, 'قيمة رقمية غير صالحة.', null];
                }

                return [$number, null, null];

            case ColumnKind::IntegerUnsigned:
                $integer = $this->parseInteger($raw);
                if ($integer === null || $integer < 0) {
                    return [null, 'يجب أن تكون القيمة عددًا صحيحًا غير سالب.', null];
                }

                return [$integer, null, null];

            case ColumnKind::Text:
                return [trim((string) $raw), null, null];

            case ColumnKind::EnumValue:
                $value = strtoupper(trim((string) $raw));
                if (! in_array($value, $column->allowed ?? [], true)) {
                    return [null, 'قيمة غير مسموحة لهذا العمود.', null];
                }

                return [$value, null, null];

            case ColumnKind::SelectedBranchCode:
                $code = trim((string) $raw);
                if ($code !== $branch->code) {
                    return [null, 'كود الفرع لا يطابق الفرع المختار.', null];
                }

                return [$code, null, $code];

            case ColumnKind::ClientBranchCode:
                $code = trim((string) $raw);
                if (! isset($branchCodeSet[$code])) {
                    return [null, 'كود الفرع لا يتبع العميل المحدد.', null];
                }

                return [$code, null, $code];

            case ColumnKind::BranchDefinitionCode:
                return [trim((string) $raw), null, null];

            case ColumnKind::ScopeBranchOrScope:
                $code = trim((string) $raw);
                if (in_array(strtoupper($code), ScopeCode::values(), true)) {
                    return [strtoupper($code), null, null];
                }
                if (isset($branchCodeSet[$code])) {
                    return [$code, null, $code];
                }

                return [null, 'النطاق يجب أن يكون كود فرع تابع للعميل أو HEAD_OFFICE أو CLIENT.', null];

            case ColumnKind::ScopeOnly:
                $value = strtoupper(trim((string) $raw));
                if (! in_array($value, ScopeCode::values(), true)) {
                    return [null, 'النطاق يجب أن يكون HEAD_OFFICE أو CLIENT.', null];
                }

                return [$value, null, null];
        }

        throw new \LogicException("Unhandled column kind for {$column->name}.");
    }

    private function singleError(string $sheet, string $column, string $reason): ImportValidationResult
    {
        return new ImportValidationResult([], [$this->error($sheet, 0, $column, null, $reason)]);
    }

    private function error(string $sheet, int $row, string $column, mixed $value, string $reason): array
    {
        return ['sheet' => $sheet, 'row' => $row, 'column' => $column, 'value' => $this->displayValue($value), 'reason' => $reason];
    }

    private function findHeaderRow(array $rows, array $anchors): ?int
    {
        foreach ($rows as $index => $row) {
            $values = array_map(fn ($cell) => is_string($cell) ? trim($cell) : $cell, $row);
            $matched = true;
            foreach ($anchors as $anchor) {
                if (! in_array($anchor, $values, true)) {
                    $matched = false;
                    break;
                }
            }
            if ($matched) {
                return $index;
            }
        }

        return null;
    }

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

    private function isEmptyRow(array $row, array $columnIndex, SheetDefinition $sheet): bool
    {
        foreach ($sheet->columnNames() as $name) {
            $value = $row[$columnIndex[$name] ?? -1] ?? null;
            if (is_string($value) ? trim($value) !== '' : $value !== null) {
                return false;
            }
        }

        return true;
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

    private function parseMonth(mixed $value): ?string
    {
        if (is_numeric($value)) {
            $date = $this->parseDate($value);

            return $date?->format('Y-m');
        }

        $trimmed = trim((string) $value);
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $trimmed) === 1) {
            return $trimmed;
        }

        return $this->parseDate($trimmed)?->format('Y-m');
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

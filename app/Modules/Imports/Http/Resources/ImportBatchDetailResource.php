<?php

namespace App\Modules\Imports\Http\Resources;

use App\Modules\Imports\Models\ImportBatch;
use App\Modules\Imports\Models\ImportRow;
use App\Modules\Imports\Support\WorkbookDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * @mixin ImportBatch
 */
class ImportBatchDetailResource extends ImportBatchResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $errors = $this->errors ?? [];
        $rows = $this->relationLoaded('rows') ? $this->rows : collect();

        return [
            ...parent::toArray($request),
            'errors' => $errors,
            'sheets' => $this->sheetSummaries($rows, $errors),
            'rows' => $rows->map(fn ($row) => [
                'sheet_name' => $row->sheet_name,
                'row_number' => $row->row_number,
                'data' => $row->data,
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, ImportRow>  $rows
     * @param  list<array{sheet:string, row:int, column:string, value:mixed, reason:string}>  $errors
     * @return list<array{sheet:string, row_count:int, error_count:int}>
     */
    private function sheetSummaries(Collection $rows, array $errors): array
    {
        $rowCounts = $rows->countBy('sheet_name');
        $errorCounts = collect($errors)->countBy('sheet');

        return collect(WorkbookDefinition::sheetNames())
            ->merge($errorCounts->keys())
            ->unique()
            ->filter(fn (string $name) => ($rowCounts[$name] ?? 0) > 0 || ($errorCounts[$name] ?? 0) > 0)
            ->map(fn (string $name) => [
                'sheet' => $name,
                'row_count' => $rowCounts[$name] ?? 0,
                'error_count' => $errorCounts[$name] ?? 0,
            ])
            ->values()
            ->all();
    }
}

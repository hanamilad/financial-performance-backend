<?php

namespace App\Modules\Imports\Http\Resources;

use App\Modules\Imports\Support\WorkbookDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ImportBatchDetailResource extends ImportBatchResource
{
    public function toArray(Request $request): array
    {
        $errors = $this->errors ?? [];
        $rows = $this->relationLoaded('rows') ? $this->rows : collect();

        return [
            ...parent::toArray($request),
            'errors' => $errors,
            'sheets' => $this->sheetSummaries($rows, $errors),
            'submitted_at' => $this->submitted_at,
            'submitted_by_name' => $this->whenLoaded('submitter', fn () => $this->submitter?->name),
            'approved_at' => $this->approved_at,
            'approved_by_name' => $this->whenLoaded('approver', fn () => $this->approver?->name),
            'published_at' => $this->published_at,
            'published_by_name' => $this->whenLoaded('publisher', fn () => $this->publisher?->name),
            'review_note' => $this->review_note,
            'rows' => $rows->map(fn ($row) => [
                'sheet_name' => $row->sheet_name,
                'row_number' => $row->row_number,
                'data' => $row->data,
            ])->values(),
        ];
    }

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

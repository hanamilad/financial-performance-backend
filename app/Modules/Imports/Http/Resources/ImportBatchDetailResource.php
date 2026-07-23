<?php

namespace App\Modules\Imports\Http\Resources;

use App\Modules\Imports\Models\ImportBatch;
use Illuminate\Http\Request;

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
        return [
            ...parent::toArray($request),
            'errors' => $this->errors ?? [],
            'rows' => $this->whenLoaded('rows', fn () => $this->rows->map(fn ($row) => [
                'row_number' => $row->row_number,
                'data' => $row->data,
            ])),
        ];
    }
}

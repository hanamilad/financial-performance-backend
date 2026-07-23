<?php

namespace App\Modules\Imports\Support;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PerformanceWorkbookReader implements WithMultipleSheets
{
    /** @var array<int, array<int, mixed>> */
    public array $salesDailyRows = [];

    public bool $salesDailySheetFound = false;

    /**
     * @return array<string, object>
     */
    public function sheets(): array
    {
        return [
            SalesDailySheet::NAME => new class($this) implements ToArray
            {
                public function __construct(private PerformanceWorkbookReader $reader) {}

                /**
                 * @param  array<int, array<int, mixed>>  $rows
                 */
                public function array(array $rows): void
                {
                    $this->reader->salesDailyRows = $rows;
                    $this->reader->salesDailySheetFound = true;
                }
            },
        ];
    }
}

<?php

namespace App\Modules\Imports\Support;

use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PerformanceWorkbookReader implements SkipsUnknownSheets, WithMultipleSheets
{
    /** @var array<string, array<int, array<int, mixed>>> */
    public array $sheetRows = [];

    /** @var array<string, bool> */
    public array $sheetFound = [];

    /**
     * Only the data sheets in WorkbookDefinition are registered, so the helper
     * sheets (README, LISTS, EXAMPLES, VALIDATION_CHECKS) are never read into
     * memory or stored.
     *
     * @return array<string, object>
     */
    public function sheets(): array
    {
        $handlers = [];

        foreach (WorkbookDefinition::sheetNames() as $name) {
            $this->sheetFound[$name] = false;
            $handlers[$name] = new class($this, $name) implements ToArray
            {
                public function __construct(
                    private PerformanceWorkbookReader $reader,
                    private string $sheet,
                ) {}

                /**
                 * @param  array<int, array<int, mixed>>  $rows
                 */
                public function array(array $rows): void
                {
                    $this->reader->sheetRows[$this->sheet] = $rows;
                    $this->reader->sheetFound[$this->sheet] = true;
                }
            };
        }

        return $handlers;
    }

    /**
     * A registered data sheet that is absent from the uploaded file is left with
     * its found flag false so the validator reports it as a missing sheet,
     * instead of maatwebsite aborting the whole read.
     *
     * @param  string|int  $sheetName
     */
    public function onUnknownSheet($sheetName): void
    {
        //
    }
}

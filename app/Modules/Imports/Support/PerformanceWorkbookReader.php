<?php

namespace App\Modules\Imports\Support;

use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class PerformanceWorkbookReader implements SkipsUnknownSheets, WithMultipleSheets
{
    public array $sheetRows = [];

    public array $sheetFound = [];

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

                public function array(array $rows): void
                {
                    $this->reader->sheetRows[$this->sheet] = $rows;
                    $this->reader->sheetFound[$this->sheet] = true;
                }
            };
        }

        return $handlers;
    }

    public function onUnknownSheet($sheetName): void {}
}

<?php

namespace App\Modules\Imports\Support;

final class ImportValidationResult
{
    /**
     * @param  list<array{sheet_name:string, row_number:int, data:array<string, mixed>}>  $validRows
     * @param  list<array{sheet:string, row:int, column:string, value:mixed, reason:string}>  $errors
     */
    public function __construct(
        public readonly array $validRows,
        public readonly array $errors,
    ) {}

    public function rowCount(): int
    {
        return count($this->validRows);
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    public function passed(): bool
    {
        return $this->errors === [];
    }
}

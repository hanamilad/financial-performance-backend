<?php

namespace App\Modules\Imports\Support;

final class ImportValidationResult
{
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

<?php

namespace App\Modules\Imports\Support;

final class SheetDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
    ) {}

    public function columnNames(): array
    {
        return array_map(fn (ColumnSpec $column) => $column->name, $this->columns);
    }

    public function requiredColumnNames(): array
    {
        return array_values(array_map(
            fn (ColumnSpec $column) => $column->name,
            array_filter($this->columns, fn (ColumnSpec $column) => $column->required),
        ));
    }

    public function anchorColumns(): array
    {
        return array_slice($this->requiredColumnNames(), 0, 2);
    }

    public function column(string $name): ?ColumnSpec
    {
        foreach ($this->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }
}

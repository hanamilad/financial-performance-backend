<?php

namespace App\Modules\Imports\Support;

final class SheetDefinition
{
    /**
     * @param  list<ColumnSpec>  $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
    ) {}

    /**
     * @return list<string>
     */
    public function columnNames(): array
    {
        return array_map(fn (ColumnSpec $column) => $column->name, $this->columns);
    }

    /**
     * @return list<string>
     */
    public function requiredColumnNames(): array
    {
        return array_values(array_map(
            fn (ColumnSpec $column) => $column->name,
            array_filter($this->columns, fn (ColumnSpec $column) => $column->required),
        ));
    }

    /**
     * The first two required columns identify the machine-header row inside the
     * sheet: their snake_case keys are distinctive enough not to collide with
     * the Arabic label or title rows above them.
     *
     * @return list<string>
     */
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

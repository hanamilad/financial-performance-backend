<?php

namespace App\Modules\Imports\Support;

final class ColumnSpec
{
    /**
     * @param  list<string>|null  $allowed  Accepted values for ColumnKind::EnumValue.
     */
    public function __construct(
        public readonly string $name,
        public readonly ColumnKind $kind,
        public readonly bool $required = true,
        public readonly ?array $allowed = null,
    ) {}

    public function referencesBranch(): bool
    {
        return in_array($this->kind, [
            ColumnKind::SelectedBranchCode,
            ColumnKind::ClientBranchCode,
            ColumnKind::ScopeBranchOrScope,
        ], true);
    }
}

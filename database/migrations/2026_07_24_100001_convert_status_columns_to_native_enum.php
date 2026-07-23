<?php

use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Imports\Enums\ImportBatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function columns(): array
    {
        return [
            ['table' => 'clients', 'column' => 'status', 'check' => 'clients_status_check', 'values' => EntityStatus::values()],
            ['table' => 'branches', 'column' => 'status', 'check' => 'branches_status_check', 'values' => EntityStatus::values()],
            ['table' => 'users', 'column' => 'role', 'check' => 'users_role_check', 'values' => UserRole::values()],
            ['table' => 'import_batches', 'column' => 'status', 'check' => 'import_batches_status_check', 'values' => ImportBatchStatus::values()],
        ];
    }

    public function up(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        foreach ($this->columns() as $column) {
            DB::statement("ALTER TABLE `{$column['table']}` DROP CHECK `{$column['check']}`");
            DB::statement("ALTER TABLE `{$column['table']}` MODIFY `{$column['column']}` ENUM({$this->valueList($column['values'])}) NOT NULL");
        }
    }

    public function down(): void
    {
        if (! $this->isMysql()) {
            return;
        }

        foreach ($this->columns() as $column) {
            DB::statement("ALTER TABLE `{$column['table']}` MODIFY `{$column['column']}` VARCHAR(255) NOT NULL");
            DB::statement("ALTER TABLE `{$column['table']}` ADD CONSTRAINT `{$column['check']}` CHECK (`{$column['column']}` IN ({$this->valueList($column['values'])}))");
        }
    }

    private function valueList(array $values): string
    {
        return collect($values)
            ->map(fn (string $value) => "'".addslashes($value)."'")
            ->implode(', ');
    }

    private function isMysql(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};

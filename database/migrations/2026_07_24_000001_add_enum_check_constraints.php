<?php

use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Imports\Enums\ImportBatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The status/role columns are stored as strings and cast to backed enums in
     * the models; these CHECK constraints stop any write outside the enum's
     * values, matching the values 1:1 so the database and the enums cannot drift.
     *
     * @return array<string, array{table: string, column: string, values: list<string>}>
     */
    private function constraints(): array
    {
        return [
            'clients_status_check' => ['table' => 'clients', 'column' => 'status', 'values' => EntityStatus::values()],
            'branches_status_check' => ['table' => 'branches', 'column' => 'status', 'values' => EntityStatus::values()],
            'users_role_check' => ['table' => 'users', 'column' => 'role', 'values' => UserRole::values()],
            'import_batches_status_check' => ['table' => 'import_batches', 'column' => 'status', 'values' => ImportBatchStatus::values()],
        ];
    }

    public function up(): void
    {
        if (! $this->supportsCheckConstraints()) {
            return;
        }

        foreach ($this->constraints() as $name => $constraint) {
            $values = collect($constraint['values'])
                ->map(fn (string $value) => "'".addslashes($value)."'")
                ->implode(', ');

            DB::statement("ALTER TABLE `{$constraint['table']}` ADD CONSTRAINT `{$name}` CHECK (`{$constraint['column']}` IN ({$values}))");
        }
    }

    public function down(): void
    {
        if (! $this->supportsCheckConstraints()) {
            return;
        }

        foreach ($this->constraints() as $name => $constraint) {
            DB::statement("ALTER TABLE `{$constraint['table']}` DROP CHECK `{$name}`");
        }
    }

    private function supportsCheckConstraints(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }
};

<?php

use App\Modules\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('InnoDB rolls back a transaction using an existing migrated table', function () {
    $before = DB::table('users')->count();

    DB::beginTransaction();

    DB::table('users')->insert([
        'name' => 'Rollback Probe',
        'email' => 'rollback-probe@example.test',
        'password' => bcrypt('secret'),
        'role' => UserRole::SystemAdmin->value,
    ]);

    expect(DB::table('users')->count())->toBe($before + 1);

    DB::rollBack();

    expect(DB::table('users')->count())->toBe($before);
});

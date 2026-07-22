<?php

use App\Modules\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('Arabic utf8mb4 content survives a database round-trip', function () {
    $arabic = 'مطعم الشرق للمأكولات — تقرير الأداء المالي 🍽️';

    DB::table('users')->insert([
        'name' => $arabic,
        'email' => 'arabic-roundtrip@example.test',
        'password' => bcrypt('secret'),
        'role' => UserRole::SystemAdmin->value,
    ]);

    $stored = DB::table('users')
        ->where('email', 'arabic-roundtrip@example.test')
        ->value('name');

    expect($stored)->toBe($arabic);
});

test('the test database uses the utf8mb4_0900_ai_ci collation', function () {
    $collation = (string) DB::selectOne('select @@collation_database as collation')->collation;

    expect($collation)->toBe('utf8mb4_0900_ai_ci');
});

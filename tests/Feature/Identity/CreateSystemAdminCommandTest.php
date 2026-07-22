<?php

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| identity:create-system-admin (AUTH-001)
|--------------------------------------------------------------------------
|
| The only way to create an administrator is this interactive command — there
| is no seeder carrying credentials. expectsQuestion() answers the prompts by
| label: name, email, password, confirmation.
|
*/

uses(RefreshDatabase::class);

it('creates a system administrator without exposing the password', function () {
    $password = 'Sup3r-Secret-Passphrase';

    $this->artisan('identity:create-system-admin')
        ->expectsQuestion('Name', 'Root Admin')
        ->expectsQuestion('Email', 'root@fpp.test')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->doesntExpectOutputToContain($password)
        ->assertSuccessful();

    $admin = User::query()->where('email', 'root@fpp.test')->sole();

    expect($admin->role)->toBe(UserRole::SystemAdmin)
        ->and($admin->name)->toBe('Root Admin')
        // Stored as a hash, never the plaintext, and verifiable.
        ->and($admin->getAuthPassword())->not->toBe($password)
        ->and(Hash::check($password, $admin->getAuthPassword()))->toBeTrue();
});

it('fails when the password confirmation does not match', function () {
    $this->artisan('identity:create-system-admin')
        ->expectsQuestion('Name', 'Root Admin')
        ->expectsQuestion('Email', 'root@fpp.test')
        ->expectsQuestion('Password', 'Sup3r-Secret-Passphrase')
        ->expectsQuestion('Confirm password', 'different-passphrase')
        ->assertFailed();

    expect(User::query()->where('email', 'root@fpp.test')->exists())->toBeFalse();
});

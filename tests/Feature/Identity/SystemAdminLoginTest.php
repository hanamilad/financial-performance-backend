<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('signs in a system administrator and exposes only the session identity', function () {
    $admin = User::factory()->systemAdmin()->create([
        'email' => 'admin@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@fpp.test',
        'password' => 'correct-horse-battery',
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.email', 'admin@fpp.test')
        ->assertJsonPath('data.role', 'system_admin')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');

    $this->assertAuthenticatedAs($admin, 'web');
});

it('rejects an incorrect password without revealing the account', function () {
    User::factory()->systemAdmin()->create([
        'email' => 'admin@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@fpp.test',
        'password' => 'wrong-password',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('refuses a client user through the admin cookie login', function () {
    User::factory()->clientUser()->create([
        'email' => 'client@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'client@fpp.test',
        'password' => 'correct-horse-battery',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('email');

    $this->assertGuest();
});

it('throttles repeated failed login attempts', function () {
    User::factory()->systemAdmin()->create([
        'email' => 'admin@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@fpp.test',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@fpp.test',
        'password' => 'wrong-password',
    ])->assertStatus(429);
});

it('rejects an unauthenticated request to the identity endpoint', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('returns the identity once authenticated', function () {
    $admin = User::factory()->systemAdmin()->create([
        'email' => 'admin@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@fpp.test',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    $this->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $admin->id)
        ->assertJsonPath('data.email', 'admin@fpp.test')
        ->assertJsonPath('data.role', 'system_admin');
});

it('ends the session on logout', function () {
    User::factory()->systemAdmin()->create([
        'email' => 'admin@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@fpp.test',
        'password' => 'correct-horse-battery',
    ])->assertOk();

    $this->assertAuthenticated('web');

    $this->postJson('/api/v1/auth/logout')->assertNoContent();

    $this->assertGuest('web');

    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('issues an XSRF-TOKEN cookie from the sanctum csrf endpoint', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

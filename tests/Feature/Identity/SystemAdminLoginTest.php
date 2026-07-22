<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| System-admin cookie login (AUTH-001)
|--------------------------------------------------------------------------
|
| These tests exercise the real stateful flow: they POST credentials to the
| login route and let the session cookie carry the identity into /me and
| /logout, rather than short-circuiting with actingAs(). Test requests use the
| host `localhost`, which is a configured stateful domain, so Sanctum resolves
| the web session exactly as the admin web panel will.
|
*/

uses(RefreshDatabase::class);

beforeEach(function () {
    // Sanctum only opens a session for requests coming from a stateful domain.
    // A browser sends that Origin automatically; the test must set it so the
    // real cookie flow — not the token fallback — is exercised. localhost:3000
    // is a configured stateful domain (the admin web dev origin).
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
    // Correct password, but the account is not a system admin: the role
    // constraint must reject it with the same generic error as a bad password.
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

    // The auth guard caches the resolved user for the lifetime of the test
    // process, which a real request (a fresh process) never does. Forget the
    // cached guards so the next request re-reads the now-invalidated session,
    // proving logout ended it server-side and not just in memory.
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('issues an XSRF-TOKEN cookie from the sanctum csrf endpoint', function () {
    $this->get('/sanctum/csrf-cookie')
        ->assertNoContent()
        ->assertCookie('XSRF-TOKEN');
});

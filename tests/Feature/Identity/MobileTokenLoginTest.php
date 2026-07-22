<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Mobile bearer-token login (AUTH-002)
|--------------------------------------------------------------------------
|
| These tests never send an Origin/Referer, so Sanctum treats every request as
| token-based (not the stateful cookie path) — exactly how the mobile app talks
| to the API.
|
*/

uses(RefreshDatabase::class);

it('issues a per-device token to a client user', function () {
    User::factory()->clientUser()->create([
        'email' => 'client@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $response = $this->postJson('/api/v1/auth/mobile/login', [
        'email' => 'client@fpp.test',
        'password' => 'correct-horse-battery',
        'device_name' => 'pixel-8',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'role']])
        ->assertJsonPath('user.email', 'client@fpp.test')
        ->assertJsonPath('user.role', 'client_user')
        ->assertJsonMissingPath('user.password');

    $plainText = $response->json('token');
    expect($plainText)->toBeString()->not->toBeEmpty();

    $stored = (string) DB::table('personal_access_tokens')->where('name', 'pixel-8')->value('token');
    // Sanctum persists only the SHA-256 hash, never the plaintext token.
    expect(strlen($stored))->toBe(64)
        ->and($plainText)->not->toContain($stored);
});

it('rejects an incorrect password', function () {
    User::factory()->clientUser()->create([
        'email' => 'client@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/mobile/login', [
        'email' => 'client@fpp.test',
        'password' => 'wrong-password',
        'device_name' => 'pixel-8',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('refuses a system admin through mobile login', function () {
    User::factory()->systemAdmin()->create([
        'email' => 'admin@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->postJson('/api/v1/auth/mobile/login', [
        'email' => 'admin@fpp.test',
        'password' => 'correct-horse-battery',
        'device_name' => 'pixel-8',
    ])->assertStatus(422)->assertJsonValidationErrors('email');

    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('throttles repeated failed mobile login attempts', function () {
    User::factory()->clientUser()->create([
        'email' => 'client@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    foreach (range(1, 5) as $ignored) {
        $this->postJson('/api/v1/auth/mobile/login', [
            'email' => 'client@fpp.test',
            'password' => 'wrong-password',
            'device_name' => 'pixel-8',
        ])->assertStatus(422);
    }

    $this->postJson('/api/v1/auth/mobile/login', [
        'email' => 'client@fpp.test',
        'password' => 'wrong-password',
        'device_name' => 'pixel-8',
    ])->assertStatus(429);
});

it('rejects the mobile identity endpoint without a token', function () {
    $this->getJson('/api/v1/auth/mobile/me')->assertUnauthorized();
});

it('returns the identity for a valid token', function () {
    $user = User::factory()->clientUser()->create([
        'email' => 'client@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);
    $token = $user->createToken('pixel-8')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/mobile/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.role', 'client_user');
});

it('logout revokes only the current device token', function () {
    $user = User::factory()->clientUser()->create([
        'email' => 'client@fpp.test',
        'password' => Hash::make('correct-horse-battery'),
    ]);
    $tokenA = $user->createToken('device-a')->plainTextToken;
    $tokenB = $user->createToken('device-b')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$tokenA)
        ->postJson('/api/v1/auth/mobile/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(1);

    // The auth guard caches the resolved user for the test process; forget it so
    // each follow-up request re-evaluates the presented token from scratch.
    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$tokenA)
        ->getJson('/api/v1/auth/mobile/me')
        ->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$tokenB)
        ->getJson('/api/v1/auth/mobile/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

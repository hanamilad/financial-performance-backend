<?php

use App\Models\User;
use App\Modules\Clients\Models\Client;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('creates a client user tied to the client as a client_user', function () {
    actingAsSystemAdmin();
    $client = Client::factory()->create();

    $this->postJson("/api/v1/admin/clients/{$client->id}/users", [
        'name' => 'Sara',
        'email' => 'sara@client.test',
        'password' => 'strong-password',
    ])->assertCreated()
        ->assertJsonPath('data.email', 'sara@client.test')
        ->assertJsonPath('data.role', 'client_user')
        ->assertJsonPath('data.client_id', $client->id)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');

    $stored = User::where('email', 'sara@client.test')->sole();
    expect($stored->role)->toBe(UserRole::ClientUser)
        ->and($stored->client_id)->toBe($client->id)
        ->and(Hash::check('strong-password', $stored->password))->toBeTrue();
});

it('rejects a duplicate client user email', function () {
    actingAsSystemAdmin();
    $client = Client::factory()->create();
    User::factory()->clientUser()->create(['email' => 'taken@client.test']);

    $this->postJson("/api/v1/admin/clients/{$client->id}/users", [
        'name' => 'Dup',
        'email' => 'taken@client.test',
        'password' => 'strong-password',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('updates a client user and can disable access', function () {
    actingAsSystemAdmin();
    $user = User::factory()->clientUser()->create(['name' => 'Old', 'is_active' => true]);

    $this->patchJson("/api/v1/admin/client-users/{$user->id}", [
        'name' => 'New',
        'is_active' => false,
    ])->assertOk()->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'New', 'is_active' => false]);
});

it('refuses to edit a system admin through the client-user endpoint', function () {
    actingAsSystemAdmin();
    $admin = User::factory()->systemAdmin()->create();

    $this->patchJson("/api/v1/admin/client-users/{$admin->id}", ['name' => 'Hacked'])
        ->assertForbidden();
});

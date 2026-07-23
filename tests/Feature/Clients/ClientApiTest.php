<?php

use App\Models\User;
use App\Modules\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists clients with counts, search and status filter', function () {
    actingAsSystemAdmin();
    Client::factory()->create(['name' => 'Alpha Foods', 'code' => 'ALPHA']);
    Client::factory()->create(['name' => 'Beta Cafe', 'code' => 'BETA']);
    Client::factory()->inactive()->create(['name' => 'Gamma Grill', 'code' => 'GAMMA']);

    $this->getJson('/api/v1/admin/clients')
        ->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonStructure([
            'data' => [['id', 'name', 'code', 'status', 'branches_count', 'users_count']],
            'meta', 'links',
        ]);

    $this->getJson('/api/v1/admin/clients?search=Alpha')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.code', 'ALPHA');

    $this->getJson('/api/v1/admin/clients?status=inactive')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.code', 'GAMMA');
});

it('creates a client defaulting to active status', function () {
    actingAsSystemAdmin();

    $this->postJson('/api/v1/admin/clients', ['name' => 'New Client', 'code' => 'NEW1'])
        ->assertCreated()
        ->assertJsonPath('data.code', 'NEW1')
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('clients', ['code' => 'NEW1', 'status' => 'active']);
});

it('rejects a duplicate client code', function () {
    actingAsSystemAdmin();
    Client::factory()->create(['code' => 'DUP']);

    $this->postJson('/api/v1/admin/clients', ['name' => 'Another', 'code' => 'DUP'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('shows a client with its branches and users', function () {
    actingAsSystemAdmin();
    $client = Client::factory()->create();
    $client->branches()->createMany([
        ['name' => 'Main', 'code' => 'M1', 'status' => 'active'],
        ['name' => 'Second', 'code' => 'M2', 'status' => 'active'],
    ]);
    User::factory()->clientUser()->for($client)->create();

    $this->getJson("/api/v1/admin/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.branches_count', 2)
        ->assertJsonPath('data.users_count', 1)
        ->assertJsonCount(2, 'data.branches')
        ->assertJsonCount(1, 'data.users')
        ->assertJsonMissingPath('data.users.0.password');
});

it('updates a client and its status', function () {
    actingAsSystemAdmin();
    $client = Client::factory()->create(['name' => 'Old', 'status' => 'active']);

    $this->patchJson("/api/v1/admin/clients/{$client->id}", [
        'name' => 'Updated',
        'status' => 'inactive',
    ])->assertOk()->assertJsonPath('data.status', 'inactive');

    $this->assertDatabaseHas('clients', ['id' => $client->id, 'name' => 'Updated', 'status' => 'inactive']);
});

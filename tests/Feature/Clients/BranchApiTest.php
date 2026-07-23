<?php

use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a branch under a client', function () {
    actingAsSystemAdmin();
    $client = Client::factory()->create();

    $this->postJson("/api/v1/admin/clients/{$client->id}/branches", [
        'name' => 'Downtown',
        'code' => 'DT',
        'city' => 'Riyadh',
    ])->assertCreated()
        ->assertJsonPath('data.client_id', $client->id)
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('branches', ['client_id' => $client->id, 'code' => 'DT', 'city' => 'Riyadh']);
});

it('enforces branch code uniqueness within a client but allows reuse across clients', function () {
    actingAsSystemAdmin();
    $clientA = Client::factory()->create();
    $clientB = Client::factory()->create();
    Branch::factory()->for($clientA)->create(['code' => 'B1']);

    $this->postJson("/api/v1/admin/clients/{$clientA->id}/branches", ['name' => 'Dup', 'code' => 'B1'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');

    $this->postJson("/api/v1/admin/clients/{$clientB->id}/branches", ['name' => 'Ok', 'code' => 'B1'])
        ->assertCreated();
});

it('updates a branch', function () {
    actingAsSystemAdmin();
    $branch = Branch::factory()->create(['name' => 'Old', 'status' => 'active']);

    $this->patchJson("/api/v1/admin/branches/{$branch->id}", [
        'name' => 'New Name',
        'status' => 'inactive',
    ])->assertOk()->assertJsonPath('data.name', 'New Name')->assertJsonPath('data.status', 'inactive');
});

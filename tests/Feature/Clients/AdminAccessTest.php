<?php

use App\Models\User;
use App\Modules\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('rejects unauthenticated access to admin APIs', function () {
    $this->getJson('/api/v1/admin/clients')->assertUnauthorized();
});

it('forbids a client_user from using admin APIs', function () {
    Sanctum::actingAs(User::factory()->clientUser()->create());

    $this->getJson('/api/v1/admin/clients')->assertForbidden();
    $this->postJson('/api/v1/admin/clients', ['name' => 'X', 'code' => 'X1'])->assertForbidden();

    $client = Client::factory()->create();
    $this->getJson("/api/v1/admin/clients/{$client->id}")->assertForbidden();
});

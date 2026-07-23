<?php

use App\Models\User;
use App\Modules\Clients\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('blocks an inactive client user from mobile login', function () {
    $client = Client::factory()->create();
    User::factory()->clientUser()->for($client)->inactive()->create([
        'email' => 'inactive@client.test',
        'password' => Hash::make('strong-password'),
    ]);

    $this->postJson('/api/v1/auth/mobile/login', [
        'email' => 'inactive@client.test',
        'password' => 'strong-password',
        'device_name' => 'device',
    ])->assertStatus(422);
});

it('blocks a client user whose client is inactive', function () {
    $client = Client::factory()->inactive()->create();
    User::factory()->clientUser()->for($client)->create([
        'email' => 'active@client.test',
        'password' => Hash::make('strong-password'),
    ]);

    $this->postJson('/api/v1/auth/mobile/login', [
        'email' => 'active@client.test',
        'password' => 'strong-password',
        'device_name' => 'device',
    ])->assertStatus(422);
});

it('blocks an inactive system admin from cookie login', function () {
    User::factory()->systemAdmin()->inactive()->create([
        'email' => 'inactive-admin@fpp.test',
        'password' => Hash::make('strong-password'),
    ]);

    $this->withHeader('Origin', 'http://localhost:3000')
        ->postJson('/api/v1/auth/login', [
            'email' => 'inactive-admin@fpp.test',
            'password' => 'strong-password',
        ])->assertStatus(422);
});

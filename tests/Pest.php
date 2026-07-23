<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->in('Feature');

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

function something() {}

function actingAsSystemAdmin(): User
{
    $admin = User::factory()->systemAdmin()->create();
    Sanctum::actingAs($admin);

    return $admin;
}

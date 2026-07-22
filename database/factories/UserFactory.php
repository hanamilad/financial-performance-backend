<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Identity\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::ClientUser,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is a system administrator.
     */
    public function systemAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::SystemAdmin,
        ]);
    }

    /**
     * Indicate that the user is a client user.
     */
    public function clientUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::ClientUser,
        ]);
    }
}

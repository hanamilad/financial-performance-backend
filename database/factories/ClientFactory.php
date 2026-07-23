<?php

namespace Database\Factories;

use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => Str::upper(fake()->unique()->bothify('CL-####')),
            'status' => EntityStatus::Active,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EntityStatus::Inactive,
        ]);
    }
}

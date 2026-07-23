<?php

namespace Database\Factories;

use App\Modules\Clients\Enums\EntityStatus;
use App\Modules\Clients\Models\Branch;
use App\Modules\Clients\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->streetName(),
            'code' => Str::upper(fake()->unique()->bothify('BR-####')),
            'city' => fake()->city(),
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

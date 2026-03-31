<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(2, true),
            'duration_minutes' => fake()->numberBetween(15, 180),
            'price' => fake()->randomFloat(2, 10, 300),
            'description' => fake()->sentence(),
            'active' => true,
        ];
    }
}
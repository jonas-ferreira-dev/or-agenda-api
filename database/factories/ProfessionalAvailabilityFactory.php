<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProfessionalAvailabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'weekday' => fake()->numberBetween(1, 5),
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_active' => true,
        ];
    }
}
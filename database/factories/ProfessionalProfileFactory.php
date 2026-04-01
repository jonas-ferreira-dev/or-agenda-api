<?php

namespace Database\Factories;

use App\Models\ProfessionalProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProfessionalProfileFactory extends Factory
{
    protected $model = ProfessionalProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($this->faker->unique()->name()),
            'public_name' => $this->faker->name(),
            'bio' => $this->faker->sentence(),
            'profile_photo' => null,
            'is_public' => true,
            'booking_enabled' => true,
        ];
    }
}
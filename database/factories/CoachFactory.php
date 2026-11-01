<?php

namespace Database\Factories;

use App\Models\Coach;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coach> */
class CoachFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'status' => 'approved', 'bio' => 'Private lessons for new and developing players.', 'specialties' => 'Fundamentals, footwork', 'hourly_rate_centavos' => 60000, 'is_accepting' => true];
    }
}

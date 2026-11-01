<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Court;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Court> */
class CourtFactory extends Factory
{
    public function definition(): array
    {
        return ['business_id' => Business::factory(), 'name' => fake()->unique()->bothify('Court ##??'), 'type' => 'Indoor', 'hourly_rate_centavos' => 40000, 'opens_at' => 6, 'closes_at' => 22];
    }
}

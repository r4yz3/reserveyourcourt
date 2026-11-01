<?php

namespace Database\Factories;

use App\Models\PlayerMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlayerMatch> */
class PlayerMatchFactory extends Factory
{
    public function definition(): array
    {
        return ['recorded_by' => User::factory(), 'played_on' => '2026-10-30', 'format' => 'singles', 'location' => 'Test Public Club', 'games' => [[11, 7], [11, 9]], 'winning_side' => 1, 'status' => 'unconfirmed'];
    }
}

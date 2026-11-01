<?php

namespace Database\Factories;

use App\Models\OpenPlay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OpenPlay> */
class OpenPlayFactory extends Factory
{
    public function definition(): array
    {
        return ['host_id' => User::factory(), 'title' => 'Test open play', 'description' => 'Test description.', 'privacy' => 'public', 'invite_token' => Str::random(40), 'location' => 'Test court', 'starts_at' => now()->addDays(2)->startOfHour(), 'ends_at' => now()->addDays(2)->startOfHour()->addHours(2), 'capacity' => 8, 'format' => 'doubles', 'gender_rule' => 'any', 'fee_centavos' => 0, 'status' => 'published'];
    }
}

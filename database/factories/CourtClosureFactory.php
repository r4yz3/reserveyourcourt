<?php

namespace Database\Factories;

use App\Models\Court;
use App\Models\CourtClosure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CourtClosure> */
class CourtClosureFactory extends Factory
{
    public function definition(): array
    {
        return ['court_id' => Court::factory(), 'business_id' => fn (array $attributes) => Court::query()->findOrFail($attributes['court_id'])->business_id, 'starts_at' => now()->addDay()->startOfDay()->addHours(2), 'ends_at' => now()->addDay()->startOfDay()->addHours(3), 'reason' => 'Court maintenance', 'created_by' => User::factory()];
    }
}

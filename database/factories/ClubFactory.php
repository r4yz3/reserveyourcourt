<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Club> */
class ClubFactory extends Factory
{
    public function definition(): array
    {
        return ['owner_id' => User::factory(), 'name' => 'Test Dinkers Club', 'description' => 'Test club description.', 'country' => 'Philippines', 'city' => 'Test City', 'is_public' => true, 'auto_approve' => true, 'members_can_post' => false, 'invite_token' => Str::random(40)];
    }

    /**
     * Give the owner an active owner membership, as the create flow does.
     */
    public function configure(): static
    {
        return $this->afterCreating(fn (Club $club) => $club->memberships()->create(['user_id' => $club->owner_id, 'role' => 'owner', 'status' => 'active']));
    }
}

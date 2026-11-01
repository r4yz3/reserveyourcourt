<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Booking> */
class BookingFactory extends Factory
{
    public function definition(): array
    {
        return ['reference' => (string) Str::uuid(), 'business_id' => Business::factory(), 'court_id' => fn (array $attributes) => Court::factory()->create(['business_id' => $attributes['business_id']])->id, 'created_by' => User::factory(), 'customer_name' => fake()->name(), 'customer_contact' => '09171234567', 'starts_at' => now()->addDay()->startOfDay()->addHours(2), 'ends_at' => now()->addDay()->startOfDay()->addHours(3), 'source' => 'online', 'status' => 'confirmed', 'payment_method' => 'cash', 'total_centavos' => 40000, 'paid_centavos' => 0, 'commission_centavos' => 0, 'request_key' => (string) Str::uuid()];
    }
}

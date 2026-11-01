<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\BookingReschedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<BookingReschedule> */
class BookingRescheduleFactory extends Factory
{
    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'business_id' => fn (array $a) => Booking::findOrFail($a['booking_id'])->business_id, 'from_court_id' => fn (array $a) => Booking::findOrFail($a['booking_id'])->court_id, 'to_court_id' => fn (array $a) => $a['from_court_id'], 'from_starts_at' => now()->addDay(), 'from_ends_at' => now()->addDay()->addHour(), 'to_starts_at' => now()->addDays(2), 'to_ends_at' => now()->addDays(2)->addHour(), 'reason' => 'rain', 'created_by' => User::factory(), 'request_key' => (string) Str::uuid()];
    }
}

<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\PaymentProof;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentProof> */
class PaymentProofFactory extends Factory
{
    public function definition(): array
    {
        return ['booking_id' => Booking::factory(), 'path' => 'receipts/test.webp', 'reference' => 'TRANSFER-123'];
    }
}

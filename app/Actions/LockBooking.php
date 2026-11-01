<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Coach;
use App\Models\Court;

class LockBooking
{
    /** Must be called within a database transaction. */
    public function handle(Booking $booking): Booking
    {
        Business::query()->whereKey($booking->business_id)->lockForUpdate()->firstOrFail();
        $current = Booking::query()->findOrFail($booking->id);
        if ($current->coach_id) {
            Coach::query()->whereKey($current->coach_id)->lockForUpdate()->firstOrFail();
        }
        Court::query()->whereKey($current->court_id)->lockForUpdate()->firstOrFail();

        return Booking::query()->lockForUpdate()->findOrFail($booking->id);
    }
}

<?php

namespace App\Console\Commands;

use App\Actions\LockBooking;
use App\Models\Booking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Release expired checkout and payment-review reservations';

    public function handle(LockBooking $lock): int
    {
        $count = 0;
        Booking::query()->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '<=', now())->select(['id', 'court_id', 'business_id'])->chunkById(100, function ($bookings) use (&$count, $lock): void {
            foreach ($bookings as $candidate) {
                DB::transaction(function () use ($candidate, &$count, $lock): void {
                    $booking = $lock->handle($candidate);
                    if (in_array($booking->status, ['held', 'awaiting_review'], true) && $booking->hold_expires_at?->lessThanOrEqualTo(now())) {
                        DB::table('inventory_claims')->where('booking_id', $booking->id)->delete();
                        $booking->update(['status' => 'expired']);
                        $count++;
                    }
                }, 3);
            }
        });
        $this->info('Expired reservations released: '.$count);

        return self::SUCCESS;
    }
}

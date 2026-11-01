<?php

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingReschedule;
use App\Models\Business;
use App\Models\Coach;
use App\Models\Court;
use App\Models\CourtClosure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RescheduleBooking
{
    public function __construct(private CreateCourtClosure $closures) {}

    /** @param array<string,mixed> $data */
    public function handle(User $actor, Booking $booking, array $data): Booking
    {
        abort_unless($actor->canAccessBusiness($booking->business_id), 404);

        return DB::transaction(function () use ($actor, $booking, $data): Booking {
            $business = Business::query()->whereKey($booking->business_id)->lockForUpdate()->firstOrFail();
            $current = Booking::query()->findOrFail($booking->id);
            $previous = BookingReschedule::query()->where('request_key', $data['request_key'])->first();
            if ($previous) {
                abort_unless($previous->booking_id === $booking->id && $previous->created_by === $actor->id, 409);

                return $current;
            }
            if ($business->status === 'suspended') {
                throw ValidationException::withMessages(['court_id' => 'This business is suspended. Contact the platform team before moving reservations.']);
            }
            $coach = $current->coach_id ? Coach::query()->whereKey($current->coach_id)->lockForUpdate()->firstOrFail() : null;
            $target = $business->courts()->whereKey($data['court_id'])->firstOrFail();
            $lockedCourts = Court::query()->whereIn('id', array_unique([$current->court_id, $target->id]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);
            $target = $lockedCourts->get($target->id);
            $original = $lockedCourts->get($booking->court_id);
            if (! $booking->canWeatherReschedule()) {
                throw ValidationException::withMessages(['booking' => 'Only fully paid, confirmed reservations can be weather-rescheduled.']);
            }
            if ($booking->reschedule_version !== (int) $data['expected_version']) {
                throw ValidationException::withMessages(['booking' => 'This booking was already moved. Refresh the page before choosing another time.']);
            }
            if (! in_array($data['reason'], ['rain', 'unplayable'], true)) {
                throw ValidationException::withMessages(['reason' => 'Choose rain or an unplayable court as the reason.']);
            }
            $start = CarbonImmutable::parse($data['starts_at'], 'Asia/Manila');
            $minutes = (int) $booking->starts_at->diffInMinutes($booking->ends_at);
            $end = $start->addMinutes($minutes);
            if (! $target->is_active || $minutes <= 0 || $minutes % 30 !== 0 || $start->isPast() || $start->minute % 30 !== 0 || $start->second !== 0 || ! $start->isSameDay($end->subSecond()) || $start->hour < $target->opens_at || $end->gt($start->startOfDay()->addHours($target->closes_at))) {
                throw ValidationException::withMessages(['starts_at' => 'Choose a future half-hour start within the replacement court’s opening hours. The original duration is kept.']);
            }
            if ($coach) {
                abort_unless($coach->businesses()->whereKey($business->id)->wherePivot('status', 'approved')->exists(), 404);
                app(CoachSchedule::class)->assertAvailable($coach, $start, $end, $booking->id, true);
            }
            $start = $start->utc();
            $end = $end->utc();
            if ($target->id === $booking->court_id && $start->equalTo($booking->starts_at)) {
                throw ValidationException::withMessages(['starts_at' => 'Choose a different time or court.']);
            }
            if (CourtClosure::query()->where('court_id', $target->id)->whereNull('cancelled_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'The replacement court is closed during that time. Choose another slot.']);
            }
            $expired = Booking::query()->where('court_id', $target->id)->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '<=', now())->pluck('id');
            DB::table('inventory_claims')->whereIn('booking_id', $expired)->delete();
            Booking::query()->whereIn('id', $expired)->update(['status' => 'expired']);
            if (DB::table('inventory_claims')->where('court_id', $target->id)->where('booking_id', '!=', $booking->id)->where('starts_at', '>=', $start)->where('starts_at', '<', $end)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'That replacement slot is already reserved. The original booking has not been changed.']);
            }
            $history = ['booking_id' => $booking->id, 'business_id' => $booking->business_id, 'from_court_id' => $booking->court_id, 'from_starts_at' => $booking->starts_at, 'from_ends_at' => $booking->ends_at, 'to_court_id' => $target->id, 'to_starts_at' => $start, 'to_ends_at' => $end, 'reason' => $data['reason'], 'staff_notes' => $data['staff_notes'] ?? null, 'created_by' => $actor->id, 'request_key' => $data['request_key']];
            $originalStart = $booking->starts_at;
            DB::table('inventory_claims')->where('booking_id', $booking->id)->delete();
            $booking->update(['court_id' => $target->id, 'starts_at' => $start, 'ends_at' => $end, 'reschedule_version' => $booking->reschedule_version + 1]);
            for ($slot = $start; $slot->lt($end); $slot = $slot->addMinutes(30)) {
                DB::table('inventory_claims')->insert(['court_id' => $target->id, 'booking_id' => $booking->id, 'starts_at' => $slot]);
            }
            if (! empty($data['block_original'])) {
                abort_unless($actor->canManageBusiness($business->id), 403);
                $now = CarbonImmutable::now('Asia/Manila');
                $floor = $now->startOfHour()->addMinutes($now->minute >= 30 ? 30 : 0);
                $closureStart = $originalStart->setTimezone('Asia/Manila')->max($floor);
                $closure = $this->closures->handle($actor, $original, ['starts_at' => $closureStart->format('Y-m-d\TH:i'), 'ends_at' => $data['closure_ends_at'], 'reason' => $data['reason'] === 'rain' ? 'Rain — court unavailable' : 'Court unplayable']);
                $history['court_closure_id'] = $closure->id;
            }
            $move = BookingReschedule::query()->create($history);
            AuditEvent::query()->create(['user_id' => $actor->id, 'business_id' => $business->id, 'action' => 'booking.weather_rescheduled', 'details' => ['booking_id' => $booking->id, 'reschedule_id' => $move->id, 'reason' => $move->reason, 'total_centavos' => $booking->total_centavos, 'paid_centavos' => $booking->paid_centavos, 'commission_centavos' => $booking->commission_centavos]]);

            return $booking;
        }, 3);
    }
}

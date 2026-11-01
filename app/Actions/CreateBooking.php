<?php

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Coach;
use App\Models\Court;
use App\Models\CourtClosure;
use App\Models\FeeSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreateBooking
{
    /** @param array<string, mixed> $data */
    public function handle(User $actor, Court $court, array $data): Booking
    {
        abort_unless($actor->canAccessBusiness($court->business_id), 404);

        return $this->reserve($actor, $court, $data);
    }

    /** @param array<string, mixed> $data */
    public function handleGuest(Court $court, array $data, string $guestKey): Booking
    {
        if (strlen($guestKey) < 32) {
            throw new \InvalidArgumentException('Guest capability must have sufficient entropy.');
        }
        $data['source'] = 'online';
        $data['payment_method'] = 'instapay';
        $data['paid_centavos'] = 0;

        return $this->reserve(null, $court, $data, hash('sha256', $guestKey));
    }

    /** @param array<string, mixed> $data */
    public function handleGuestLesson(Court $court, Coach $coach, array $data, string $guestKey): Booking
    {
        if (strlen($guestKey) < 32) {
            throw new \InvalidArgumentException('Guest capability must have sufficient entropy.');
        }
        $data['source'] = 'online';
        $data['payment_method'] = 'instapay';
        $data['paid_centavos'] = 0;

        return $this->reserve(null, $court, $data, hash('sha256', $guestKey), $coach);
    }

    /** @param array<string, mixed> $data */
    private function reserve(?User $actor, Court $court, array $data, ?string $guestHash = null, ?Coach $coach = null): Booking
    {
        return DB::transaction(function () use ($actor, $court, $data, $guestHash, $coach): Booking {
            $business = Business::query()->whereKey($court->business_id)->lockForUpdate()->firstOrFail();
            if ($guestHash !== null) {
                abort_unless($business->status === 'approved' && ! $business->is_demo && filled($business->payment_instructions), 404);
            }
            if ($business->status === 'suspended') {
                throw ValidationException::withMessages(['business' => 'This business is suspended and cannot accept new reservations.']);
            }
            if ($coach) {
                $coach = Coach::query()->whereKey($coach->id)->lockForUpdate()->firstOrFail();
            }
            $court = Court::query()->whereKey($court->id)->lockForUpdate()->firstOrFail();
            $existing = Booking::query()->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless(($guestHash !== null ? is_string($existing->guest_key_hash) && hash_equals($existing->guest_key_hash, $guestHash) : $existing->created_by === $actor->id) && $existing->court_id === $court->id && $existing->coach_id === $coach?->id, 409);

                return $existing;
            }

            $start = CarbonImmutable::parse($data['starts_at'], 'Asia/Manila');
            $end = $start->addMinutes((int) $data['duration']);
            if (! $court->is_active || $start->isPast() || $start->second !== 0 || $start->minute % 30 !== 0 || ! $start->isSameDay($end->subSecond()) || $start->hour < $court->opens_at || $end->greaterThan($start->startOfDay()->addHours($court->closes_at))) {
                throw ValidationException::withMessages(['starts_at' => 'Choose a future half-hour slot within the court opening hours.']);
            }
            if (! in_array((int) $data['duration'], [30, 60, 90, 120, 150, 180, 240], true)) {
                throw ValidationException::withMessages(['duration' => 'Choose a supported duration.']);
            }

            $expired = Booking::query()->where('court_id', $court->id)->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '<=', now())->pluck('id');
            DB::table('inventory_claims')->whereIn('booking_id', $expired)->delete();
            Booking::query()->whereIn('id', $expired)->update(['status' => 'expired']);

            $startUtc = $start->utc();
            $endUtc = $end->utc();
            if (DB::table('inventory_claims')->where('court_id', $court->id)->where('starts_at', '>=', $startUtc)->where('starts_at', '<', $endUtc)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This court is already reserved during that time. Choose another slot.']);
            }

            if (CourtClosure::query()->where('court_id', $court->id)->whereNull('cancelled_at')->where('starts_at', '<', $endUtc)->where('ends_at', '>', $startUtc)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This court is unavailable during that time. Choose another slot.']);
            }

            if ($coach) {
                abort_unless($coach->businesses()->whereKey($business->id)->wherePivot('status', 'approved')->exists(), 404);
                app(CoachSchedule::class)->assertAvailable($coach, $startUtc, $endUtc);
            }
            $fee = FeeSetting::query()->latest('id')->first();
            if (($data['source'] ?? 'online') !== 'online') {
                throw ValidationException::withMessages(['source' => 'Walk-in reservations are no longer supported.']);
            }
            if (! $fee) {
                throw ValidationException::withMessages(['source' => 'The super admin must configure the booking fee before online reservations can be recorded.']);
            }
            $coaching = $coach ? intdiv($coach->hourly_rate_centavos * (int) $data['duration'], 60) : 0;
            if ($coach && $coaching <= 0) {
                throw ValidationException::withMessages(['coach' => 'The coach must configure a coaching rate before accepting lessons.']);
            }
            $total = intdiv($court->hourly_rate_centavos * (int) $data['duration'], 60) + $coaching;
            $paid = (int) ($data['paid_centavos'] ?? 0);
            if (($data['payment_method'] === 'cash' && $paid !== $total) || ($data['payment_method'] === 'instapay' && $paid !== 0)) {
                throw ValidationException::withMessages(['paid_amount' => 'Full payment is required to confirm a reservation. Record the exact rental amount for cash; InstaPay transfers require separate full-payment verification.']);
            }

            $booking = Booking::query()->create([
                'reference' => (string) Str::uuid(), 'business_id' => $court->business_id,
                'court_id' => $court->id, 'coach_id' => $coach?->id, 'coaching_centavos' => $coaching, 'created_by' => $actor?->id, 'guest_key_hash' => $guestHash,
                'customer_name' => $data['customer_name'], 'customer_contact' => $data['customer_contact'],
                'starts_at' => $startUtc, 'ends_at' => $endUtc, 'source' => 'online',
                'status' => $data['payment_method'] === 'cash' ? 'confirmed' : 'held',
                'payment_method' => $data['payment_method'], 'total_centavos' => $total,
                'paid_centavos' => $paid, 'commission_centavos' => $fee->amount_centavos,
                'fee_setting_id' => $fee->id,
                'request_key' => $data['request_key'],
                'hold_expires_at' => $data['payment_method'] === 'instapay' ? now()->addMinutes(15)->min($startUtc) : null,
                'notes' => $data['notes'] ?? null,
            ]);
            for ($slot = $startUtc; $slot->lessThan($endUtc); $slot = $slot->addMinutes(30)) {
                DB::table('inventory_claims')->insert(['court_id' => $court->id, 'booking_id' => $booking->id, 'starts_at' => $slot]);
            }
            AuditEvent::query()->create(['user_id' => $actor?->id, 'business_id' => $court->business_id, 'action' => 'booking.created', 'details' => ['booking_id' => $booking->id, 'source' => $booking->source, 'commission_centavos' => $booking->commission_centavos, 'paid_centavos' => $booking->paid_centavos]]);

            return $booking;
        }, 3);
    }
}

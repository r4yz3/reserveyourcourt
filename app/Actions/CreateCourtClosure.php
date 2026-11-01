<?php

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\Court;
use App\Models\CourtClosure;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateCourtClosure
{
    /** @param array<string,mixed> $data */
    public function handle(User $actor, Court $court, array $data): CourtClosure
    {
        abort_unless($actor->canManageBusiness($court->business_id), 403);

        return DB::transaction(function () use ($actor, $court, $data): CourtClosure {
            Business::query()->whereKey($court->business_id)->lockForUpdate()->firstOrFail();
            $court = Court::query()->whereKey($court->id)->lockForUpdate()->firstOrFail();
            $start = CarbonImmutable::parse($data['starts_at'], 'Asia/Manila');
            $end = CarbonImmutable::parse($data['ends_at'], 'Asia/Manila');
            if ($start->minute % 30 !== 0 || $end->minute % 30 !== 0 || $start->second !== 0 || $end->second !== 0 || $start->lt(now('Asia/Manila')->startOfHour()->addMinutes(now('Asia/Manila')->minute >= 30 ? 30 : 0)) || $end->lte($start) || $end->gt($start->addDays(7))) {
                throw ValidationException::withMessages(['starts_at' => 'Use half-hour boundaries from the current time onward, for a closure of at most seven days.']);
            }
            $start = $start->utc();
            $end = $end->utc();
            if ($court->bookings()->where('starts_at', '<', $end)->where('ends_at', '>', $start)->where(fn ($q) => $q->whereIn('status', ['confirmed', 'completed', 'no_show'])->orWhere(fn ($p) => $p->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '>', now())))->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This closure overlaps an existing reservation or payment hold. Resolve the booking first; it will not be cancelled automatically.']);
            }
            if (CourtClosure::query()->where('court_id', $court->id)->whereNull('cancelled_at')->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'This court already has a closure during that time.']);
            }
            $closure = CourtClosure::query()->create(['business_id' => $court->business_id, 'court_id' => $court->id, 'starts_at' => $start, 'ends_at' => $end, 'reason' => $data['reason'], 'created_by' => $actor->id]);
            AuditEvent::query()->create(['user_id' => $actor->id, 'business_id' => $court->business_id, 'action' => 'court.closure_created', 'details' => ['closure_id' => $closure->id, 'court_id' => $court->id, 'starts_at' => $start->toIso8601String(), 'ends_at' => $end->toIso8601String()]]);

            return $closure;
        }, 3);
    }
}

<?php

namespace App\Actions;

use App\Models\Court;
use App\Models\CourtClosure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AvailableCourtSlots
{
    /**
     * @param  Collection<int, Court>  $courts
     * @return array<int, list<string>>
     */
    public function handle(Collection $courts, CarbonImmutable $date, int $duration, ?int $excludeBookingId = null): array
    {
        if ($duration <= 0 || $duration % 30 !== 0) {
            return [];
        }
        $claims = DB::table('inventory_claims')->join('bookings', 'bookings.id', '=', 'inventory_claims.booking_id')->whereIn('inventory_claims.court_id', $courts->pluck('id'))->when($excludeBookingId !== null, fn ($query) => $query->where('inventory_claims.booking_id', '!=', $excludeBookingId))->where('inventory_claims.starts_at', '>=', $date->utc())->where('inventory_claims.starts_at', '<', $date->addDay()->utc())
            ->where(fn ($q) => $q->whereIn('bookings.status', ['confirmed', 'completed', 'no_show'])->orWhere(fn ($p) => $p->whereIn('bookings.status', ['held', 'awaiting_review'])->where('bookings.hold_expires_at', '>', now())))
            ->get(['inventory_claims.court_id', 'inventory_claims.starts_at']);
        $occupied = [];
        foreach ($claims as $claim) {
            $occupied[$claim->court_id][CarbonImmutable::parse($claim->starts_at, 'UTC')->timestamp] = true;
        }
        $closures = CourtClosure::query()->whereIn('court_id', $courts->pluck('id'))->whereNull('cancelled_at')->where('starts_at', '<', $date->addDay()->utc())->where('ends_at', '>', $date->utc())->get();
        foreach ($closures as $closure) {
            $from = $closure->starts_at->max($date->utc());
            $until = $closure->ends_at->min($date->addDay()->utc());
            for ($time = $from; $time->lt($until); $time = $time->addMinutes(30)) {
                $occupied[$closure->court_id][$time->timestamp] = true;
            }
        }
        $slots = [];
        foreach ($courts as $court) {
            $slots[$court->id] = [];
            for ($time = $date->addHours($court->opens_at); $time->addMinutes($duration)->lte($date->addHours($court->closes_at)); $time = $time->addMinutes(30)) {
                if ($time->isPast()) {
                    continue;
                }
                $available = true;
                for ($offset = 0; $offset < $duration; $offset += 30) {
                    if (isset($occupied[$court->id][$time->addMinutes($offset)->timestamp])) {
                        $available = false;
                        break;
                    }
                }
                if ($available) {
                    $slots[$court->id][] = $time->format('Y-m-d\TH:i');
                }
            }
        }

        return $slots;
    }
}

<?php

namespace App\Actions;

use App\Models\Coach;
use App\Models\Court;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CoachSchedule
{
    public function __construct(private AvailableCourtSlots $courts) {}

    public function activeLessons(Coach $coach): Builder
    {
        return $coach->bookings()->getQuery()->where(fn ($q) => $q->whereIn('status', ['confirmed', 'completed', 'no_show'])->orWhere(fn ($hold) => $hold->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '>', now())));
    }

    public function assertAvailable(Coach $coach, CarbonImmutable $start, CarbonImmutable $end, ?int $excludeBookingId = null, bool $existingLesson = false): void
    {
        $local = $start->setTimezone('Asia/Manila');
        $finish = $end->setTimezone('Asia/Manila');
        $start = $start->utc();
        $end = $end->utc();
        $window = $coach->availability()->where('weekday', $local->dayOfWeek)->lockForUpdate()->first();
        $startMinute = $local->hour * 60 + $local->minute;
        $endMinute = (int) $local->startOfDay()->diffInMinutes($finish);
        if ($coach->status !== 'approved' || (! $coach->is_accepting && ! $existingLesson) || ! $window || ! $local->isSameDay($finish->subSecond()) || $startMinute < $window->start_minute || $endMinute > $window->end_minute) {
            throw ValidationException::withMessages(['starts_at' => 'The coach is not available for this lesson time. Choose another slot.']);
        }
        if ($coach->blocks()->where('starts_at', '<', $end)->where('ends_at', '>', $start)->lockForUpdate()->first(['id']) !== null || $this->activeLessons($coach)->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))->where('starts_at', '<', $end)->where('ends_at', '>', $start)->lockForUpdate()->first(['id']) !== null) {
            throw ValidationException::withMessages(['starts_at' => 'The coach already has a lesson or time off during this slot, including lessons at other venues.']);
        }
    }

    /** @param Collection<int,Court> $courts @return array<int,list<string>> */
    public function slots(Coach $coach, Collection $courts, CarbonImmutable $date, int $duration, ?int $excludeBookingId = null, bool $existingLesson = false): array
    {
        $slots = $this->courts->handle($courts, $date, $duration, $excludeBookingId);
        $window = $coach->availability()->where('weekday', $date->dayOfWeek)->first();
        $busy = $this->activeLessons($coach)->when($excludeBookingId, fn ($q) => $q->where('id', '!=', $excludeBookingId))->where('starts_at', '<', $date->addDay()->utc())->where('ends_at', '>', $date->utc())->get(['starts_at', 'ends_at']);
        $blocks = $coach->blocks()->where('starts_at', '<', $date->addDay()->utc())->where('ends_at', '>', $date->utc())->get();
        foreach ($slots as $courtId => $times) {
            $slots[$courtId] = array_values(array_filter($times, function ($value) use ($coach, $duration, $window, $busy, $blocks, $existingLesson): bool {
                if ($coach->status !== 'approved' || (! $coach->is_accepting && ! $existingLesson) || ! $window) {
                    return false;
                }
                $start = CarbonImmutable::parse($value, 'Asia/Manila');
                $end = $start->addMinutes($duration);
                $minute = $start->hour * 60 + $start->minute;
                if ($minute < $window->start_minute || $minute + $duration > $window->end_minute) {
                    return false;
                }

                return ! $busy->concat($blocks)->contains(fn ($range) => $range->starts_at->lt($end) && $range->ends_at->gt($start));
            }));
        }

        return $slots;
    }
}

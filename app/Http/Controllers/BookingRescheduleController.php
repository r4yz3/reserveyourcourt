<?php

namespace App\Http\Controllers;

use App\Actions\AvailableCourtSlots;
use App\Actions\CoachSchedule;
use App\Actions\RescheduleBooking;
use App\Http\Requests\RescheduleBookingRequest;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingRescheduleController extends Controller
{
    public function edit(Request $request, Booking $booking, AvailableCourtSlots $availability): View
    {
        abort_unless($request->user()->canAccessBusiness($booking->business_id), 404);
        $booking->load(['court', 'business', 'coach.user']);
        $local = $booking->starts_at->setTimezone('Asia/Manila');
        $now = CarbonImmutable::now('Asia/Manila');
        $floor = $now->startOfHour()->addMinutes($now->minute >= 30 ? 30 : 0);

        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$now->toDateString(), 'before_or_equal:'.$now->addDays(90)->toDateString()],
            'court' => ['nullable', 'integer'],
        ]);
        $courts = $booking->business->courts()->where('is_active', true)->orderBy('name')->get();
        $oldCourt = $request->old('court_id');
        $selectedCourt = isset($data['court']) ? $courts->firstWhere('id', (int) $data['court']) : ($courts->firstWhere('id', (int) ($oldCourt ?? $booking->court_id)) ?? $courts->first());
        abort_if(isset($data['court']) && ! $selectedCourt, 404);
        $defaultDate = $local->max($now)->addDay()->min($now->addDays(90))->toDateString();
        $date = CarbonImmutable::parse($data['date'] ?? $defaultDate, 'Asia/Manila')->startOfDay();
        $duration = (int) $booking->starts_at->diffInMinutes($booking->ends_at);
        $available = $selectedCourt ? ($booking->coach ? app(CoachSchedule::class)->slots($booking->coach, new Collection([$selectedCourt]), $date, $duration, $booking->id, true) : $availability->handle(new Collection([$selectedCourt]), $date, $duration, $booking->id)) : [];
        $slots = $available[$selectedCourt?->id] ?? [];
        if ($selectedCourt?->id === $booking->court_id) {
            $slots = array_values(array_filter($slots, fn ($slot) => $slot !== $local->format('Y-m-d\TH:i')));
        }

        return view('bookings.reschedule', [
            'booking' => $booking, 'courts' => $courts, 'selectedCourt' => $selectedCourt, 'date' => $date, 'duration' => $duration, 'slots' => $slots,
            'requestKey' => (string) Str::uuid(),
            'closureEnd' => $booking->ends_at->setTimezone('Asia/Manila')->max($floor->addHour())->format('Y-m-d\TH:i'),
            'canBlock' => $request->user()->canManageBusiness($booking->business_id),
        ]);
    }

    public function update(RescheduleBookingRequest $request, Booking $booking, RescheduleBooking $action): RedirectResponse
    {
        $booking = $action->handle($request->user(), $booking, $request->validated());

        return redirect()->route('bookings.show', $booking)->with('success', 'Booking moved. Payment and platform fee are unchanged. Share the updated time with the player.');
    }
}

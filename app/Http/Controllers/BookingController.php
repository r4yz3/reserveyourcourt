<?php

namespace App\Http\Controllers;

use App\Actions\CreateBooking;
use App\Actions\LockBooking;
use App\Http\Requests\StoreBookingRequest;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Business;
use App\Models\FeeSetting;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(Request $request, Business $business): View
    {
        abort_unless($request->user()->canAccessBusiness($business->id), 404);

        $prefill = $request->validate([
            'court' => ['nullable', 'integer'],
            'start' => ['nullable', 'date_format:Y-m-d\\TH:i'],
        ]);

        return view('bookings.create', ['business' => $business, 'courts' => $business->courts()->where('is_active', true)->orderBy('name')->get(), 'requestKey' => (string) Str::uuid(), 'fee' => FeeSetting::query()->latest('id')->first(), 'selectedCourt' => $prefill['court'] ?? null, 'startAt' => $prefill['start'] ?? now('Asia/Manila')->addDay()->setTime(9, 0)->format('Y-m-d\\TH:i')]);
    }

    public function store(StoreBookingRequest $request, Business $business, CreateBooking $action): RedirectResponse
    {
        abort_unless($request->user()->canAccessBusiness($business->id), 404);
        $court = $business->courts()->findOrFail($request->integer('court_id'));
        $data = $request->validated();
        $data['source'] = 'online';
        $data['paid_centavos'] = Money::centavos((string) ($data['paid_amount'] ?? '0'));
        $booking = $action->handle($request->user(), $court, $data);

        return redirect()->route('bookings.show', $booking)->with('success', 'Booking saved.');
    }

    public function show(Request $request, Booking $booking): View
    {
        abort_unless($request->user()->canAccessBusiness($booking->business_id), 404);
        $booking->load(['court', 'business', 'coach.user', 'proofs', 'reschedules.fromCourt', 'reschedules.toCourt', 'reschedules.actor']);

        return view('bookings.show', compact('booking'));
    }

    public function update(Request $request, Booking $booking, LockBooking $lock): RedirectResponse
    {
        abort_unless($request->user()->canAccessBusiness($booking->business_id), 404);
        $data = $request->validate(['action' => ['required', 'in:cancel,complete,cash'],
            'request_key' => ['required_if:action,cash', 'nullable', 'uuid'], 'amount' => ['required_if:action,cash', 'nullable', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/D', 'numeric', 'min:0.01']]);
        $data['amount_centavos'] = isset($data['amount']) ? Money::centavos((string) $data['amount']) : null;
        DB::transaction(function () use ($request, $booking, $data, $lock): void {
            $booking = $lock->handle($booking);
            if ($data['action'] === 'cash') {
                $previous = AuditEvent::query()->where('request_key', $data['request_key'])->first();
                if ($previous) {
                    abort_unless($previous->user_id === $request->user()->id && ($previous->details['booking_id'] ?? null) === $booking->id, 409);

                    return;
                }
            }
            if ($data['action'] === 'cancel') {
                abort_unless(in_array($booking->status, ['held', 'awaiting_review', 'confirmed'], true), 409, 'This booking can no longer be cancelled.');
                $booking->update(['status' => 'cancelled', 'hold_expires_at' => null]);
                DB::table('inventory_claims')->where('booking_id', $booking->id)->delete();
            } elseif ($data['action'] === 'complete') {
                abort_unless($booking->status === 'confirmed' && $booking->ends_at->isPast(), 409, 'Only a confirmed booking that has ended can be completed.');
                $booking->update(['status' => 'completed']);
            } else {
                abort_unless($booking->status === 'confirmed' && $booking->payment_method === 'cash', 409, 'Only confirmed cash bookings accept cash records.');
                abort_if($booking->paid_centavos + (int) $data['amount_centavos'] > $booking->total_centavos, 422, 'Payment exceeds the remaining balance.');
                $booking->increment('paid_centavos', (int) $data['amount_centavos']);
            }
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $booking->business_id, 'request_key' => $data['action'] === 'cash' ? $data['request_key'] : null, 'action' => 'booking.'.$data['action'], 'details' => ['booking_id' => $booking->id, 'amount_centavos' => $data['amount_centavos'] ?? null]]);
        }, 3);

        return back()->with('success', 'Booking updated. Any refund must be arranged separately by the venue.');
    }
}

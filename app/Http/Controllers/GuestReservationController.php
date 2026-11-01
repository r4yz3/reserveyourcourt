<?php

namespace App\Http\Controllers;

use App\Actions\CreateBooking;
use App\Actions\SubmitPaymentProof;
use App\Http\Requests\StoreGuestReservationRequest;
use App\Models\Booking;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GuestReservationController extends Controller
{
    public function store(StoreGuestReservationRequest $request, Business $business, CreateBooking $create): RedirectResponse
    {
        abort_unless($business->status === 'approved' && ! $business->is_demo, 404);
        $court = $business->courts()->where('is_active', true)->findOrFail($request->integer('court_id'));
        if (! $request->session()->has('player_key')) {
            $request->session()->put('player_key', Str::random(64));
        }
        $booking = $create->handleGuest($court, $request->validated() + ['player_id' => $request->user()?->id], $request->session()->get('player_key'));

        return redirect()->route('public.reservations.show', ['reference' => $booking->reference]);
    }

    public function show(Request $request, string $reference): View
    {
        $booking = $this->authorizedBooking($request, $reference)->load(['court', 'business', 'coach.user', 'proofs', 'reschedules.fromCourt', 'reschedules.toCourt']);

        return view('public.reservation', compact('booking'));
    }

    public function storeProof(Request $request, string $reference, SubmitPaymentProof $submit): RedirectResponse
    {
        $booking = $this->authorizedBooking($request, $reference);
        abort_unless($booking->status === 'held', 409, 'This payment attempt is no longer accepting receipts.');
        $data = $request->validate(['reference' => ['required', 'string', 'max:120'], 'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000']]);
        $submit->handle($booking, $request->file('receipt'), $data['reference']);

        return redirect()->route('public.reservations.show', ['reference' => $booking->reference])->with('success', 'Receipt submitted. Your reservation is not confirmed until the venue verifies full payment.');
    }

    private function authorizedBooking(Request $request, string $reference): Booking
    {
        $booking = Booking::query()->where('reference', $reference)->firstOrFail();
        if ($booking->player_id !== null && $request->user()?->id === $booking->player_id) {
            return $booking;
        }
        $key = $request->session()->get('player_key');
        abort_unless(is_string($key) && is_string($booking->guest_key_hash) && hash_equals($booking->guest_key_hash, hash('sha256', $key)), 404);

        return $booking;
    }
}

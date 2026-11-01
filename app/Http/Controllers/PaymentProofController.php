<?php

namespace App\Http\Controllers;

use App\Actions\LockBooking;
use App\Actions\SubmitPaymentProof;
use App\Http\Requests\StoreProofRequest;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentProof;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function store(StoreProofRequest $request, Booking $booking, SubmitPaymentProof $submit): RedirectResponse
    {
        abort_unless($request->user()->canAccessBusiness($booking->business_id), 404);
        $submit->handle($booking, $request->file('receipt'), $request->validated('reference'), $request->user());

        return back()->with('success', 'Receipt submitted. Verify actual full funds received before approving payment.');
    }

    public function show(Request $request, PaymentProof $proof): StreamedResponse
    {
        abort_unless($request->user()->canAccessBusiness($proof->booking->business_id), 404);

        return Storage::disk(config('filesystems.receipts_disk'))->response($proof->path, 'receipt.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function update(Request $request, PaymentProof $proof, LockBooking $lock): RedirectResponse
    {
        abort_unless($request->user()->canAccessBusiness($proof->booking->business_id), 404);
        $data = $request->validate([
            'decision' => ['required', 'in:verify,reject'],
            'received_amount' => ['exclude_unless:decision,verify', 'required', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/D'],
        ]);
        DB::transaction(function () use ($request, $proof, $data, $lock): void {
            $booking = $lock->handle($proof->booking);
            $proof = PaymentProof::query()->lockForUpdate()->findOrFail($proof->id);
            abort_unless($proof->status === 'submitted', 409, 'This receipt was already reviewed.');
            abort_unless($booking->status === 'confirmed' || ($booking->status === 'awaiting_review' && $booking->hold_expires_at?->isFuture()), 409, 'Reservation expired or cancelled. Resolve the transfer separately; the court cannot be reclaimed automatically.');
            if ($data['decision'] === 'verify' && Money::centavos((string) $data['received_amount']) !== $booking->total_centavos) {
                throw ValidationException::withMessages(['received_amount' => 'Verify receipt of the exact full booking amount before confirming this reservation.']);
            }
            $proof->update(['status' => $data['decision'] === 'verify' ? 'verified' : 'rejected', 'reviewed_by' => $request->user()->id]);
            if ($data['decision'] === 'verify') {
                $booking->update(['paid_centavos' => $booking->total_centavos, 'status' => 'confirmed', 'hold_expires_at' => null]);
            } elseif ($booking->status === 'awaiting_review') {
                $booking->update(['status' => 'held', 'hold_expires_at' => now()->addMinutes(15)->min($booking->starts_at)]);
            }
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $booking->business_id, 'action' => 'proof.'.$data['decision'], 'details' => ['proof_id' => $proof->id, 'booking_id' => $booking->id]]);
        }, 3);

        return back()->with('success', 'Receipt reviewed. Approval records full payment; refunds are handled separately.');
    }
}

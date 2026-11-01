<?php

namespace App\Actions;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SubmitPaymentProof
{
    public function __construct(private OptimizeReceipt $optimizer, private LockBooking $lock) {}

    public function handle(Booking $booking, UploadedFile $file, string $reference, ?User $actor = null): void
    {
        $this->assertAccepting($booking);
        $path = $this->optimizer->handle($file, $booking->business_id);
        try {
            DB::transaction(function () use ($booking, $path, $reference, $actor): void {
                $booking = $this->lock->handle($booking);
                $this->assertAccepting($booking);
                abort_if($booking->proofs()->where('status', 'submitted')->exists(), 409, 'A receipt is already awaiting review.');
                $proof = $booking->proofs()->create(['path' => $path, 'reference' => $reference]);
                if ($booking->status === 'held') {
                    $booking->update(['status' => 'awaiting_review', 'hold_expires_at' => now()->addHours(2)->min($booking->starts_at)]);
                }
                AuditEvent::query()->create(['user_id' => $actor?->id, 'business_id' => $booking->business_id, 'action' => 'proof.submitted', 'details' => ['booking_id' => $booking->id, 'proof_id' => $proof->id]]);
            }, 3);
        } catch (\Throwable $exception) {
            Storage::disk(config('filesystems.receipts_disk'))->delete($path);
            throw $exception;
        }
    }

    private function assertAccepting(Booking $booking): void
    {
        abort_unless($booking->payment_method === 'instapay' && in_array($booking->status, ['held', 'confirmed'], true), 409);
        abort_if($booking->status === 'held' && ! $booking->hold_expires_at?->isFuture(), 409, 'This payment hold has expired. Check availability before sending money.');
    }
}

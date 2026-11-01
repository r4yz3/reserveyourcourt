<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\User;

class ClaimGuestBookings
{
    /**
     * Attach guest bookings made in this browser session to the signed-in player.
     *
     * The session's player key is the same capability the guest checkout already
     * trusts, so no booking reference or contact detail is accepted as proof.
     */
    public function handle(User $user, mixed $playerKey): int
    {
        if (! is_string($playerKey) || strlen($playerKey) < 32) {
            return 0;
        }

        return Booking::query()
            ->whereNull('player_id')
            ->where('guest_key_hash', hash('sha256', $playerKey))
            ->update(['player_id' => $user->id]);
    }
}

<?php

namespace App\Actions;

use App\Models\Booking;
use App\Models\PlayerMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordPlayerMatch
{
    /**
     * Record a match from the recorder's point of view. The recorder always plays on side 1.
     *
     * Players named by email are linked only when that email belongs to an account, and the
     * recorder is never told whether it did, so the form cannot be used to probe for accounts:
     * any match naming an opponent email waits for confirmation, linked or not.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(User $recorder, array $data): PlayerMatch
    {
        return DB::transaction(function () use ($recorder, $data): PlayerMatch {
            $booking = isset($data['booking_id']) ? Booking::query()->with('business')->where('player_id', $recorder->id)->findOrFail($data['booking_id']) : null;
            $games = collect($data['games'])->map(fn (array $game): array => [(int) $game['mine'], (int) $game['theirs']])->all();
            $won = collect($games)->filter(fn (array $game): bool => $game[0] > $game[1])->count();
            $isDoubles = $data['format'] === 'doubles';
            $entries = collect([
                [1, $recorder->name, $recorder->id],
                $isDoubles ? [1, $data['partner_name'], $this->accountId($data['partner_email'] ?? null)] : null,
                [2, $data['opponent_name'], $this->accountId($data['opponent_email'] ?? null)],
                $isDoubles ? [2, $data['second_opponent_name'], $this->accountId($data['second_opponent_email'] ?? null)] : null,
            ])->filter()->values();

            $linked = [];
            $entries = $entries->map(function (array $entry) use (&$linked): array {
                if ($entry[2] !== null && in_array($entry[2], $linked, true)) {
                    $entry[2] = null;
                }
                if ($entry[2] !== null) {
                    $linked[] = $entry[2];
                }

                return $entry;
            });

            $match = PlayerMatch::query()->create([
                'recorded_by' => $recorder->id,
                'booking_id' => $booking?->id,
                'played_on' => $data['played_on'],
                'format' => $data['format'],
                'location' => $booking?->business->name ?? ($data['location'] ?? null),
                'games' => $games,
                'winning_side' => $won * 2 > count($games) ? 1 : 2,
                'status' => filled($data['opponent_email'] ?? null) || filled($data['second_opponent_email'] ?? null) ? 'unconfirmed' : 'personal',
            ]);
            foreach ($entries as [$side, $name, $userId]) {
                $match->players()->create(['side' => $side, 'name' => $name, 'user_id' => $userId]);
            }

            return $match;
        });
    }

    private function accountId(?string $email): ?int
    {
        return filled($email) ? User::query()->where('email', $email)->value('id') : null;
    }
}

<?php

namespace App\Actions;

use App\Models\OpenPlay;
use App\Models\OpenPlayParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class JoinOpenPlay
{
    /**
     * Join an open play, or take a waitlist place once it is full. The event row is locked so two
     * players can never take the last spot at the same time.
     */
    public function join(OpenPlay $openPlay, User $player): OpenPlayParticipant
    {
        return DB::transaction(function () use ($openPlay, $player): OpenPlayParticipant {
            $openPlay = OpenPlay::query()->lockForUpdate()->findOrFail($openPlay->id);
            if (! $openPlay->isJoinable()) {
                throw ValidationException::withMessages(['join' => 'This open play is no longer accepting players.']);
            }
            if ($openPlay->host_id === $player->id) {
                throw ValidationException::withMessages(['join' => 'You are hosting this open play.']);
            }
            if ($problem = $openPlay->eligibilityProblem($player)) {
                throw ValidationException::withMessages(['join' => $problem]);
            }
            $existing = $openPlay->participants()->where('user_id', $player->id)->first();
            if ($existing && $existing->status !== 'left') {
                return $existing;
            }
            $joined = $openPlay->participants()->where('status', 'joined')->count();
            $status = $joined < $openPlay->capacity ? 'joined' : 'waitlisted';

            return $existing
                ? tap($existing)->update(['status' => $status])
                : $openPlay->participants()->create(['user_id' => $player->id, 'status' => $status]);
        });
    }

    /**
     * Leave an open play. When a joined player leaves, the longest-waiting player takes the spot.
     */
    public function leave(OpenPlay $openPlay, User $player): void
    {
        DB::transaction(function () use ($openPlay, $player): void {
            $openPlay = OpenPlay::query()->lockForUpdate()->findOrFail($openPlay->id);
            $entry = $openPlay->participants()->where('user_id', $player->id)->whereIn('status', ['joined', 'waitlisted'])->first();
            if (! $entry) {
                return;
            }
            $wasJoined = $entry->status === 'joined';
            $entry->update(['status' => 'left']);
            if ($wasJoined && $openPlay->starts_at->isFuture()) {
                $openPlay->participants()->where('status', 'waitlisted')->orderBy('updated_at')->orderBy('id')->first()?->update(['status' => 'joined']);
            }
        });
    }
}

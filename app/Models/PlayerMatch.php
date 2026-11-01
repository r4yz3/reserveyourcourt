<?php

namespace App\Models;

use Database\Factories\PlayerMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['recorded_by', 'booking_id', 'played_on', 'format', 'location', 'games', 'winning_side', 'status', 'reviewed_by', 'reviewed_at'])]
class PlayerMatch extends Model
{
    /** @use HasFactory<PlayerMatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['played_on' => 'immutable_date', 'games' => 'array', 'winning_side' => 'integer', 'reviewed_at' => 'immutable_datetime'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class)->orderBy('side')->orderBy('id');
    }

    /**
     * The side (1 or 2) the given user played on, or null when they did not play.
     */
    public function sideFor(User $user): ?int
    {
        return $this->players->firstWhere('user_id', $user->id)?->side;
    }

    /**
     * Opponents with accounts may confirm or dispute a result recorded by the other side.
     */
    public function canBeReviewedBy(User $user): bool
    {
        $recorderSide = $this->sideFor($this->recorder);
        $side = $this->sideFor($user);

        return $this->status === 'unconfirmed' && $side !== null && $recorderSide !== null && $side !== $recorderSide;
    }
}

<?php

namespace App\Models;

use Database\Factories\OpenPlayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['host_id', 'club_id', 'business_id', 'title', 'description', 'privacy', 'invite_token', 'passkey_hash', 'location', 'starts_at', 'ends_at', 'capacity', 'format', 'min_rating', 'max_rating', 'gender_rule', 'fee_centavos', 'payment_note', 'status'])]
#[Hidden(['invite_token', 'passkey_hash'])]
class OpenPlay extends Model
{
    /** @use HasFactory<OpenPlayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'capacity' => 'integer', 'fee_centavos' => 'integer', 'min_rating' => 'decimal:2', 'max_rating' => 'decimal:2'];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(OpenPlayParticipant::class)->orderBy('id');
    }

    public function venueName(): string
    {
        return $this->business?->name ?? ($this->location ?: 'Location to be announced');
    }

    public function isUpcoming(): bool
    {
        return $this->ends_at->isFuture();
    }

    public function isJoinable(): bool
    {
        return $this->status === 'published' && $this->starts_at->isFuture();
    }

    /**
     * Why the player may not join, or null when they are eligible. The host decides the rules;
     * players see the reason instead of a silently disabled button.
     */
    public function eligibilityProblem(User $user): ?string
    {
        if ($this->gender_rule === 'men' && $user->gender !== 'male') {
            return 'This open play is for men. Set your gender in your profile if this applies to you.';
        }
        if ($this->gender_rule === 'women' && $user->gender !== 'female') {
            return 'This open play is for women. Set your gender in your profile if this applies to you.';
        }
        if (($this->min_rating !== null || $this->max_rating !== null) && $user->self_rating === null) {
            return 'This open play has a rating range. Add your self rating to your profile to join.';
        }
        if ($this->min_rating !== null && (float) $user->self_rating < (float) $this->min_rating) {
            return 'Your self rating is below this open play\'s range.';
        }
        if ($this->max_rating !== null && (float) $user->self_rating > (float) $this->max_rating) {
            return 'Your self rating is above this open play\'s range.';
        }

        return null;
    }

    public function ratingRange(): ?string
    {
        return match (true) {
            $this->min_rating !== null && $this->max_rating !== null => number_format((float) $this->min_rating, 2).'–'.number_format((float) $this->max_rating, 2),
            $this->min_rating !== null => number_format((float) $this->min_rating, 2).'+',
            $this->max_rating !== null => 'Up to '.number_format((float) $this->max_rating, 2),
            default => null,
        };
    }

    public function formatLabel(): string
    {
        return ['doubles' => 'Doubles', 'singles' => 'Singles', 'mixed_rotation' => 'Rotation'][$this->format] ?? 'Doubles';
    }

    public function genderLabel(): string
    {
        return ['any' => 'Open to all', 'men' => "Men's", 'women' => "Women's", 'mixed' => 'Mixed'][$this->gender_rule] ?? 'Open to all';
    }
}

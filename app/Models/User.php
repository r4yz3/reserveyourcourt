<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'home_city', 'alias', 'gender', 'bio', 'self_rating', 'contact_number', 'country', 'address', 'photo_path', 'photo_disk', 'show_profile', 'show_location', 'show_rating', 'show_activity', 'show_stats', 'profile_prompt_dismissed_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    public function coach(): HasOne
    {
        return $this->hasOne(Coach::class);
    }

    public function playerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'player_id');
    }

    public function hostedOpenPlays(): HasMany
    {
        return $this->hasMany(OpenPlay::class, 'host_id');
    }

    public function clubMemberships(): HasMany
    {
        return $this->hasMany(ClubMember::class);
    }

    public function openPlayEntries(): HasMany
    {
        return $this->hasMany(OpenPlayParticipant::class);
    }

    /**
     * The name other players see: the alias when one is set.
     */
    public function displayName(): string
    {
        return filled($this->alias) ? $this->alias : $this->name;
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->displayName(), 0, 1));
    }

    /**
     * A short label for a self rating; null means not rated (NR).
     */
    public static function ratingLabel(float|string|null $rating): string
    {
        if ($rating === null || $rating === '') {
            return 'Not rated';
        }

        return match (true) {
            $rating < 2.5 => 'Beginner',
            $rating < 3.0 => 'Novice',
            $rating < 3.5 => 'Intermediate',
            $rating < 4.0 => 'Strong intermediate',
            $rating < 4.5 => 'Advanced',
            default => 'Expert',
        };
    }

    public function ratingBadge(): string
    {
        return $this->self_rating === null ? 'NR' : number_format((float) $this->self_rating, 2);
    }

    /**
     * Details that make open play matching work. Missing any of them triggers the completion prompt.
     */
    public function isProfileIncomplete(): bool
    {
        return blank($this->gender) || blank($this->contact_number) || blank($this->home_city);
    }

    public function shouldPromptProfile(): bool
    {
        return $this->isProfileIncomplete() && ($this->profile_prompt_dismissed_at === null || $this->profile_prompt_dismissed_at->lt(now()->subDays(7)));
    }

    public function matchEntries(): HasMany
    {
        return $this->hasMany(MatchPlayer::class);
    }

    /**
     * Staff and coaches keep their workspaces; everyone else lands in the player area.
     */
    public function homeRoute(): string
    {
        if ($this->is_super_admin || $this->businesses()->exists()) {
            return 'dashboard';
        }

        return $this->coach()->exists() ? 'coach.portal' : 'play.dashboard';
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)->withPivot('role');
    }

    public function canManageBusiness(int $businessId): bool
    {
        return $this->is_super_admin || $this->businesses()->whereKey($businessId)->wherePivot('role', 'owner')->exists();
    }

    public function canAccessBusiness(int $businessId): bool
    {
        return $this->is_super_admin || $this->businesses()->whereKey($businessId)->exists();
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'self_rating' => 'decimal:2',
            'show_profile' => 'boolean',
            'show_location' => 'boolean',
            'show_rating' => 'boolean',
            'show_activity' => 'boolean',
            'show_stats' => 'boolean',
            'profile_prompt_dismissed_at' => 'immutable_datetime',
        ];
    }
}

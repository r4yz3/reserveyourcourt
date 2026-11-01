<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'skill_level', 'home_city'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    public function coach(): HasOne
    {
        return $this->hasOne(Coach::class);
    }

    public function playerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'player_id');
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
        ];
    }
}

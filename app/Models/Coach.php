<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'status', 'bio', 'specialties', 'hourly_rate_centavos', 'is_accepting'])]
class Coach extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_accepting' => 'boolean', 'hourly_rate_centavos' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)->withPivot('status')->withTimestamps();
    }

    public function availability(): HasMany
    {
        return $this->hasMany(CoachAvailability::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(CoachBlock::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}

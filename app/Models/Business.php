<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'city', 'address', 'payment_instructions', 'is_demo', 'status', 'description', 'photo_path', 'photo_disk', 'photo_width', 'photo_height'])]
class Business extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_demo' => 'boolean'];
    }

    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(Coach::class)->withPivot('status')->withTimestamps();
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['booking_id', 'business_id', 'from_court_id', 'to_court_id', 'from_starts_at', 'from_ends_at', 'to_starts_at', 'to_ends_at', 'reason', 'staff_notes', 'created_by', 'court_closure_id', 'request_key'])]
class BookingReschedule extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['from_starts_at' => 'immutable_datetime', 'from_ends_at' => 'immutable_datetime', 'to_starts_at' => 'immutable_datetime', 'to_ends_at' => 'immutable_datetime'];
    }

    public function fromCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'from_court_id');
    }

    public function toCourt(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'to_court_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reasonLabel(): string
    {
        return $this->reason === 'rain' ? 'Rain / unsafe weather' : 'Court unplayable';
    }
}

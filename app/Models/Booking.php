<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['reference', 'business_id', 'court_id', 'created_by', 'customer_name', 'customer_contact', 'starts_at', 'ends_at', 'source', 'status', 'payment_method', 'total_centavos', 'paid_centavos', 'commission_centavos', 'fee_setting_id', 'request_key', 'hold_expires_at', 'notes', 'guest_key_hash', 'reschedule_version', 'coach_id', 'coaching_centavos', 'lesson_attendance', 'coach_notes', 'player_id'])]
#[Hidden(['guest_key_hash', 'reschedule_version', 'coach_notes'])]
class Booking extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'hold_expires_at' => 'immutable_datetime', 'total_centavos' => 'integer', 'paid_centavos' => 'integer', 'commission_centavos' => 'integer', 'reschedule_version' => 'integer', 'coaching_centavos' => 'integer'];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_id');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class);
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(BookingReschedule::class)->orderByDesc('id');
    }

    public function canWeatherReschedule(): bool
    {
        return $this->status === 'confirmed' && $this->total_centavos > 0 && $this->paid_centavos === $this->total_centavos;
    }

    public function shortReference(): string
    {
        return strtoupper(substr($this->reference, 0, 8));
    }
}

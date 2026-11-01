<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['coach_id', 'weekday', 'start_minute', 'end_minute'])]
class CoachAvailability extends Model
{
    protected function casts(): array
    {
        return ['weekday' => 'integer', 'start_minute' => 'integer', 'end_minute' => 'integer'];
    }
}

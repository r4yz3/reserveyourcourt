<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['open_play_id', 'user_id', 'status'])]
class OpenPlayParticipant extends Model
{
    public function openPlay(): BelongsTo
    {
        return $this->belongsTo(OpenPlay::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

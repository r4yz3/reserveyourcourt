<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['player_match_id', 'side', 'user_id', 'name'])]
class MatchPlayer extends Model
{
    protected function casts(): array
    {
        return ['side' => 'integer'];
    }

    public function playerMatch(): BelongsTo
    {
        return $this->belongsTo(PlayerMatch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

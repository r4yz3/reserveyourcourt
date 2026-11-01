<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'business_id', 'action', 'details', 'request_key'])]
class AuditEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}

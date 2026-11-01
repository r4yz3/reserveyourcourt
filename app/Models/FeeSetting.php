<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['amount_centavos', 'changed_by'])]
class FeeSetting extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amount_centavos' => 'integer'];
    }
}

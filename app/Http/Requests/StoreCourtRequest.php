<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:80'], 'type' => ['required', 'in:Indoor,Outdoor,Covered'], 'hourly_rate' => ['required', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/D', 'numeric', 'min:1'], 'opens_at' => ['required', 'integer', 'between:0,23'], 'closes_at' => ['required', 'integer', 'between:1,24', 'gt:opens_at']];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'city' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:255']];
    }
}

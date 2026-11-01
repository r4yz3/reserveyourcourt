<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $business = $this->route('business');
        abort_unless($this->user()?->canAccessBusiness($business->id), 404);

        return $this->user()->canManageBusiness($business->id);
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:120'], 'city' => ['required', 'string', 'max:100'], 'address' => ['nullable', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'payment_instructions' => ['nullable', 'string', 'max:2000']];
    }
}

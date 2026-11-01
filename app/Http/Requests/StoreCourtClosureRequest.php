<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCourtClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $court = $this->route('court');
        $business = $this->route('business');
        abort_unless($court->business_id === $business->id && $this->user()?->canAccessBusiness($business->id), 404);

        return $this->user()->canManageBusiness($business->id);
    }

    public function rules(): array
    {
        return ['starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'], 'reason' => ['required', 'string', 'max:255']];
    }
}

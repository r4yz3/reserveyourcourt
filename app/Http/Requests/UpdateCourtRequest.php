<?php

namespace App\Http\Requests;

class UpdateCourtRequest extends StoreCourtRequest
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
        return array_merge(parent::rules(), ['is_active' => ['required', 'boolean']]);
    }
}

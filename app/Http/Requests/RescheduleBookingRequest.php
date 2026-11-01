<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');
        abort_unless($this->user()?->canAccessBusiness($booking->business_id), 404);

        return ! $this->boolean('block_original') || $this->user()->canManageBusiness($booking->business_id);
    }

    public function rules(): array
    {
        return ['court_id' => ['required', 'integer'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'], 'reason' => ['required', 'in:rain,unplayable'], 'staff_notes' => ['nullable', 'string', 'max:1000'], 'expected_version' => ['required', 'integer', 'min:0'], 'request_key' => ['required', 'uuid'], 'block_original' => ['sometimes', 'boolean'], 'closure_ends_at' => ['exclude_unless:block_original,1', 'required', 'date_format:Y-m-d\TH:i'], 'total_centavos' => ['prohibited'], 'paid_centavos' => ['prohibited'], 'commission_centavos' => ['prohibited'], 'duration' => ['prohibited']];
    }
}

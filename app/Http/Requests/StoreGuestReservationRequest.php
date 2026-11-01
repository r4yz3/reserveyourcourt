<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGuestReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('selection'))) {
            $parts = explode('|', $this->input('selection'), 2);
            $this->merge(['court_id' => $parts[0] ?? null, 'starts_at' => $parts[1] ?? null]);
        }
    }

    public function rules(): array
    {
        return ['court_id' => ['required', 'integer'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'before:'.now('Asia/Manila')->addDays(91)->startOfDay()->format('Y-m-d H:i:s')], 'duration' => ['required', 'integer', 'in:30,60,90,120,150,180,240'], 'customer_name' => ['required', 'string', 'max:120'], 'customer_contact' => ['required', 'string', 'max:120'], 'request_key' => ['required', 'uuid'], 'accept_terms' => ['accepted'], 'payment_method' => ['prohibited'], 'paid_amount' => ['prohibited'], 'paid_centavos' => ['prohibited'], 'status' => ['prohibited'], 'source' => ['prohibited']];
    }

    public function attributes(): array
    {
        return ['court_id' => 'court time', 'starts_at' => 'start time', 'customer_name' => 'name', 'customer_contact' => 'phone or email', 'accept_terms' => 'full-payment acknowledgement'];
    }
}

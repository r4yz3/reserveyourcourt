<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['court_id' => ['required', 'integer'], 'customer_name' => ['required', 'string', 'max:120'], 'customer_contact' => ['required', 'string', 'max:120'], 'starts_at' => ['required', 'date_format:Y-m-d\\TH:i'], 'duration' => ['required', 'integer', 'in:30,60,90,120,150,180,240'], 'source' => ['sometimes', 'in:online'], 'payment_method' => ['required', 'in:cash,instapay'], 'paid_amount' => ['nullable', 'regex:/^\d{1,7}(?:\.\d{1,2})?$/D'], 'notes' => ['nullable', 'string', 'max:2000'], 'request_key' => ['required', 'uuid']];
    }
}

<?php

namespace App\Http\Requests;

class StoreCoachLessonRequest extends StoreGuestReservationRequest
{
    public function rules(): array
    {
        return array_replace(parent::rules(), ['business_id' => ['required', 'integer'], 'duration' => ['required', 'integer', 'in:30,60,90,120'], 'coach_id' => ['prohibited'], 'coaching_centavos' => ['prohibited'], 'total_centavos' => ['prohibited'], 'commission_centavos' => ['prohibited']]);
    }
}

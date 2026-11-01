<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerProfileRequest extends FormRequest
{
    public const COUNTRIES = ['Philippines', 'Singapore', 'Malaysia', 'Indonesia', 'Thailand', 'Vietnam', 'Japan', 'South Korea', 'Australia', 'United States', 'Canada', 'Other'];

    public const VISIBILITY = ['show_profile', 'show_location', 'show_rating', 'show_activity', 'show_stats'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * The completion dialog sends only a subset of fields; the full form also sends visibility switches.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['self_rating' => $this->input('self_rating') === 'NR' ? null : $this->input('self_rating')]);
        if ($this->routeIs('play.profile.update')) {
            $this->merge(collect(self::VISIBILITY)->mapWithKeys(fn (string $field): array => [$field => $this->boolean($field)])->all());
        }
    }

    public function rules(): array
    {
        $full = $this->routeIs('play.profile.update');

        return [
            'name' => [$full ? 'required' : 'prohibited', 'string', 'max:120'],
            'alias' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', 'in:male,female,other'],
            'bio' => [$full ? 'nullable' : 'prohibited', 'string', 'max:500'],
            'self_rating' => ['nullable', 'numeric', 'between:2,5.5', 'decimal:0,2'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'country' => ['nullable', 'in:'.implode(',', self::COUNTRIES)],
            'home_city' => ['nullable', 'string', 'max:120'],
            'address' => [$full ? 'nullable' : 'prohibited', 'string', 'max:255'],
            ...collect(self::VISIBILITY)->mapWithKeys(fn (string $field): array => [$field => [$full ? 'boolean' : 'prohibited']])->all(),
        ];
    }

    public function messages(): array
    {
        return [
            'contact_number.regex' => 'Enter a phone number using digits, spaces, dashes or a leading +.',
            'self_rating.between' => 'Self ratings run from 2.00 to 5.50.',
        ];
    }
}

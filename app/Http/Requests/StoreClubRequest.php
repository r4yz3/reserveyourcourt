<?php

namespace App\Http\Requests;

use App\Models\Club;
use Illuminate\Foundation\Http\FormRequest;

class StoreClubRequest extends FormRequest
{
    public function authorize(): bool
    {
        $club = $this->route('club');

        return $club instanceof Club ? $club->isManager($this->user()) : $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['is_public', 'auto_approve', 'members_can_post'])->mapWithKeys(fn (string $field): array => [$field => $this->boolean($field)])->all());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'description' => ['nullable', 'string', 'max:3000'],
            'country' => ['required', 'in:'.implode(',', UpdatePlayerProfileRequest::COUNTRIES)],
            'city' => ['nullable', 'string', 'max:120'],
            'facebook_url' => ['nullable', 'url:https', 'max:255', 'regex:/^https:\/\/(www\.|m\.)?facebook\.com\//i'],
            'website_url' => ['nullable', 'url:https', 'max:255'],
            'is_public' => ['boolean'],
            'auto_approve' => ['boolean'],
            'members_can_post' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'facebook_url.regex' => 'Use a facebook.com link, starting with https://.',
            'facebook_url.url' => 'Use a full https:// link.',
            'website_url.url' => 'Use a full https:// link.',
        ];
    }
}

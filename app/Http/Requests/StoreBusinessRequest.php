<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['owner_email' => mb_strtolower(trim((string) $this->input('owner_email')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'], 'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'], 'owner_mode' => ['required', 'in:existing,new'],
            'owner_email' => ['required', 'email', 'max:255', $this->input('owner_mode') === 'new' ? Rule::unique('users', 'email') : Rule::exists('users', 'email')],
            'owner_name' => ['exclude_unless:owner_mode,new', 'required', 'string', 'max:120'],
            'password' => ['exclude_unless:owner_mode,new', 'required', 'confirmed', 'max:72', Password::min(8)->letters()->numbers()],
        ];
    }
}

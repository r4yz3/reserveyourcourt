<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlayerMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Drop empty game rows and normalise player emails before validation.
     */
    protected function prepareForValidation(): void
    {
        $games = collect($this->input('games', []))->filter(fn ($game): bool => is_array($game) && (filled($game['mine'] ?? null) || filled($game['theirs'] ?? null)))->values()->all();
        $emails = collect(['partner_email', 'opponent_email', 'second_opponent_email'])->mapWithKeys(fn (string $field): array => [$field => is_string($this->input($field)) ? strtolower(trim($this->input($field))) : $this->input($field)]);
        $this->merge(['games' => $games] + $emails->all());
    }

    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'integer'],
            'played_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2020-01-01', 'before_or_equal:'.now('Asia/Manila')->toDateString()],
            'format' => ['required', 'in:singles,doubles'],
            'location' => ['nullable', 'string', 'max:160'],
            'partner_name' => ['nullable', 'required_if:format,doubles', 'string', 'max:120'],
            'partner_email' => ['nullable', 'email', 'max:255'],
            'opponent_name' => ['required', 'string', 'max:120'],
            'opponent_email' => ['nullable', 'email', 'max:255'],
            'second_opponent_name' => ['nullable', 'required_if:format,doubles', 'string', 'max:120'],
            'second_opponent_email' => ['nullable', 'email', 'max:255'],
            'games' => ['required', 'array', 'min:1', 'max:5'],
            'games.*.mine' => ['required', 'integer', 'min:0', 'max:99'],
            'games.*.theirs' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }

    public function messages(): array
    {
        return [
            'games.required' => 'Enter the score of at least one game.',
            'games.*.mine.required' => 'Enter both scores for each game.',
            'games.*.theirs.required' => 'Enter both scores for each game.',
            'partner_name.required_if' => 'Add your partner for a doubles match.',
            'second_opponent_name.required_if' => 'Add both opponents for a doubles match.',
            'played_on.before_or_equal' => 'Scores can only be recorded for matches already played.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $games = collect($this->input('games'));
            if ($games->contains(fn (array $game): bool => (int) $game['mine'] === (int) $game['theirs'])) {
                $validator->errors()->add('games', 'A game cannot end in a tie. Check the scores.');

                return;
            }
            $won = $games->filter(fn (array $game): bool => (int) $game['mine'] > (int) $game['theirs'])->count();
            if ($won * 2 === $games->count()) {
                $validator->errors()->add('games', 'Games are split evenly. Add the deciding game.');
            }
            $emails = collect([$this->input('partner_email'), $this->input('opponent_email'), $this->input('second_opponent_email')])->filter();
            if ($emails->contains($this->user()->email) || $emails->duplicates()->isNotEmpty()) {
                $validator->errors()->add('opponent_email', 'Each player needs a different email address, and not your own.');
            }
            if ($this->filled('booking_id') && ! $this->user()->playerBookings()->whereKey($this->integer('booking_id'))->exists()) {
                $validator->errors()->add('booking_id', 'Choose one of your own bookings.');
            }
        }];
    }
}

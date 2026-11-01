<?php

namespace App\Http\Requests;

use App\Models\Business;
use App\Models\Club;
use App\Models\OpenPlay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOpenPlayRequest extends FormRequest
{
    public function authorize(): bool
    {
        $openPlay = $this->route('openPlay');

        return $openPlay instanceof OpenPlay ? $openPlay->host_id === $this->user()?->id : $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['required', 'in:public,private,club'],
            'club_id' => ['nullable', 'required_if:privacy,club', 'integer'],
            'passkey' => ['nullable', 'string', 'min:4', 'max:32'],
            'remove_passkey' => ['nullable', 'boolean'],
            'business_id' => ['nullable', 'integer'],
            'location' => ['nullable', 'required_without:business_id', 'string', 'max:160'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'duration' => ['required', 'integer', 'in:60,90,120,150,180,240,300,360'],
            'capacity' => ['required', 'integer', 'min:2', 'max:64'],
            'format' => ['required', 'in:doubles,singles,mixed_rotation'],
            'gender_rule' => ['required', 'in:any,men,women,mixed'],
            'min_rating' => ['nullable', 'numeric', 'between:2,5.5', 'decimal:0,2'],
            'max_rating' => ['nullable', 'numeric', 'between:2,5.5', 'decimal:0,2', 'gte:min_rating'],
            'fee' => ['nullable', 'numeric', 'min:0', 'max:10000', 'decimal:0,2'],
            'payment_note' => ['nullable', 'string', 'max:255'],
            'intent' => ['required', 'in:draft,publish'],
        ];
    }

    public function messages(): array
    {
        return [
            'location.required_without' => 'Choose a listed venue or enter where you will play.',
            'max_rating.gte' => 'The highest rating must be at or above the lowest rating.',
            'duration.in' => 'Choose a supported duration.',
            'club_id.required_if' => 'Choose a club for a club-only open play.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->filled('business_id') && ! Business::query()->whereKey($this->integer('business_id'))->where('status', 'approved')->where('is_demo', false)->exists()) {
                $validator->errors()->add('business_id', 'Choose a listed venue.');
            }
            if ($this->filled('club_id') && ! Club::query()->find($this->integer('club_id'))?->isManager($this->user())) {
                $validator->errors()->add('club_id', 'You can only host open play for clubs you own or moderate.');
            }
            if ($this->input('intent') === 'publish' && $this->startsAt()->isPast()) {
                $validator->errors()->add('date', 'Choose a future start time before publishing.');
            }
            $openPlay = $this->route('openPlay');
            if ($openPlay instanceof OpenPlay && $this->integer('capacity') < $openPlay->participants()->where('status', 'joined')->count()) {
                $validator->errors()->add('capacity', 'Capacity cannot go below the number of players already joined.');
            }
        }];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d H:i', $this->input('date').' '.$this->input('start_time'), 'Asia/Manila');
    }

    /**
     * Attributes for the open play record, in storage form.
     *
     * @return array<string, mixed>
     */
    public function openPlayAttributes(): array
    {
        $startsAt = $this->startsAt();

        return [
            'title' => $this->input('title'),
            'description' => $this->input('description'),
            'privacy' => $this->input('privacy'),
            'club_id' => $this->filled('club_id') ? $this->integer('club_id') : null,
            'business_id' => $this->filled('business_id') ? $this->integer('business_id') : null,
            'location' => $this->filled('business_id') ? null : $this->input('location'),
            'starts_at' => $startsAt->utc(),
            'ends_at' => $startsAt->addMinutes($this->integer('duration'))->utc(),
            'capacity' => $this->integer('capacity'),
            'format' => $this->input('format'),
            'gender_rule' => $this->input('gender_rule'),
            'min_rating' => $this->input('min_rating'),
            'max_rating' => $this->input('max_rating'),
            'fee_centavos' => (int) round(((float) $this->input('fee', 0)) * 100),
            'payment_note' => $this->input('payment_note'),
        ];
    }
}

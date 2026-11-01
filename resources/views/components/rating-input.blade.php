@props(['name' => 'self_rating', 'value' => null, 'id' => 'self_rating'])
@php
    $current = $value === null || $value === '' ? null : number_format((float) $value, 2, '.', '');
    $bands = ['NR' => null, 'Beginner' => '2.25', 'Novice' => '2.75', 'Intermediate' => '3.25', 'Strong intermediate' => '3.75', 'Advanced' => '4.25', 'Expert' => '4.75'];
@endphp
<div class="rating-input" data-rating-input>
    <div class="rating-bands" role="group" aria-label="Rating band">
        @foreach($bands as $label => $preset)
            <button type="button" class="rating-band" data-rating-preset="{{ $preset ?? 'NR' }}" aria-pressed="{{ ($preset === null && $current === null) || ($current !== null && $preset !== null && \App\Models\User::ratingLabel($current) === $label) ? 'true' : 'false' }}">{{ $label === 'NR' ? 'Not rated' : $label }}</button>
        @endforeach
    </div>
    <div class="rating-dial">
        <input id="{{ $id }}" name="{{ $name }}" type="number" inputmode="decimal" min="2" max="5.5" step="0.05" value="{{ $current }}" placeholder="NR" aria-describedby="{{ $id }}_label" data-rating-number>
        <input type="range" min="2" max="5.5" step="0.05" value="{{ $current ?? '2' }}" aria-label="Self rating slider" data-rating-range @if($current === null) data-unset @endif>
        <span class="rating-label" id="{{ $id }}_label" data-rating-label>{{ \App\Models\User::ratingLabel($current) }}</span>
    </div>
</div>

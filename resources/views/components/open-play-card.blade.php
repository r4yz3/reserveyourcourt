@props(['event'])
@php
    $start = $event->starts_at->setTimezone('Asia/Manila');
    $spotsLeft = max(0, $event->capacity - ($event->joined_count ?? 0));
@endphp
<a class="open-play-card" href="{{ route('open-play.show', $event) }}">
    <div class="open-play-card-head">
        <span class="next-game-date"><strong>{{ $start->format('j') }}</strong>{{ $start->format('M') }}</span>
        <div><h3>{{ $event->title }}</h3><p class="small muted">{{ $start->format('D · g:i A') }}–{{ $event->ends_at->setTimezone('Asia/Manila')->format('g:i A') }}</p></div>
    </div>
    <p class="open-play-venue"><x-icon name="pin" size="14" />{{ $event->venueName() }}</p>
    <div class="open-play-tags">
        <span>{{ $event->formatLabel() }}</span>
        @if($event->gender_rule !== 'any')<span>{{ $event->genderLabel() }}</span>@endif
        @if($range = $event->ratingRange())<span>{{ $range }}</span>@endif
    </div>
    <div class="open-play-card-foot">
        <span class="spots {{ $spotsLeft === 0 ? 'full' : '' }}">{{ $spotsLeft === 0 ? 'Full · waitlist open' : $spotsLeft.' of '.$event->capacity.' spots left' }}</span>
        <strong>{{ $event->fee_centavos > 0 ? '₱'.number_format($event->fee_centavos / 100, 2) : 'Free' }}</strong>
    </div>
</a>

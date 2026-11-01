@props(['match', 'user', 'actions' => true])
@php
    $side = $match->sideFor($user);
    $won = $match->winning_side === $side;
    $games = collect($match->games)->map(fn (array $game): array => $side === 1 ? $game : [$game[1], $game[0]]);
    $partners = $match->players->where('side', $side)->where('user_id', '!==', $user->id)->pluck('name');
    $opponents = $match->players->where('side', '!==', $side)->pluck('name');
    [$tone, $label] = match ($match->status) {
        'confirmed' => ['confirmed', 'Confirmed'],
        'unconfirmed' => ['pending', 'Awaiting confirmation'],
        'disputed' => ['suspended', 'Disputed'],
        default => ['draft', 'Personal log'],
    };
@endphp
<article class="match-row">
    <span class="match-result {{ $won ? 'won' : 'lost' }}" aria-label="{{ $won ? 'Win' : 'Loss' }}">{{ $won ? 'W' : 'L' }}</span>
    <div class="match-body">
        <div class="match-title"><strong>vs {{ $opponents->join(' & ') }}</strong>@if($partners->isNotEmpty())<span class="muted small">with {{ $partners->join(' & ') }}</span>@endif</div>
        <p class="small muted">{{ $match->played_on->format('D, j M Y') }} · {{ ucfirst($match->format) }}@if($match->location) · {{ $match->location }}@endif</p>
    </div>
    <div class="match-games" aria-label="Game scores">@foreach($games as [$mine,$theirs])<span class="{{ $mine > $theirs ? 'game-won' : '' }}">{{ $mine }}–{{ $theirs }}</span>@endforeach</div>
    <div class="match-meta">
        <span class="status {{ $tone }}">{{ $label }}</span>
        @if($actions && $match->canBeReviewedBy($user))
            <div class="match-review">
                <form method="post" action="{{ route('play.scores.review', $match) }}">@csrf @method('patch')<input type="hidden" name="decision" value="confirm"><button class="button compact">Confirm</button></form>
                <form method="post" action="{{ route('play.scores.review', $match) }}">@csrf @method('patch')<input type="hidden" name="decision" value="dispute"><button class="button secondary compact">Dispute</button></form>
            </div>
        @elseif($actions && $match->recorded_by === $user->id && $match->status !== 'confirmed')
            <form method="post" action="{{ route('play.scores.destroy', $match) }}" class="match-remove">@csrf @method('delete')<button class="text-button small">Remove</button></form>
        @endif
    </div>
</article>

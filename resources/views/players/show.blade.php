@extends('layouts.public')
@section('title',$player->displayName())
@section('private','true')
@section('content')
<div class="public-container player-area">
    <a class="back-link" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('play.dashboard') }}"><x-icon name="left" size="14" />Back</a>
    <div class="player-card-layout">
        <section class="player-card">
            <div class="player-card-top">
                <x-player-avatar :user="$player" :size="88" />
                <div class="player-card-identity">
                    <div class="player-card-name"><h1>{{ $player->displayName() }}</h1>@if($showRating)<span class="rating-chip" title="{{ \App\Models\User::ratingLabel($player->self_rating) }}">{{ $player->ratingBadge() }}</span>@endif</div>
                    @if($showRating)<p class="small muted">{{ \App\Models\User::ratingLabel($player->self_rating) }}@if($player->self_rating !== null) · self-rated @endif</p>@endif
                    @if($showLocation && $player->home_city)<p class="small muted"><x-icon name="pin" size="13" />{{ $player->home_city }}, {{ $player->country }}</p>@endif
                </div>
            </div>
            @if($player->bio)<p class="player-card-bio">{{ $player->bio }}</p>@endif
            @if($isOwner)
                <div class="player-card-owner"><a class="button secondary compact" href="{{ route('play.profile') }}"><x-icon name="settings" size="14" />Edit profile</a>@unless($player->show_profile)<span class="small muted">Only you can see this card.</span>@endunless</div>
            @endif
            @if($showStats)
                <dl class="player-card-stats">
                    <div><dt>Clubs</dt><dd>{{ $clubs }}</dd></div>
                    <div><dt>Played</dt><dd>{{ $played }}</dd></div>
                    <div><dt>Organized</dt><dd>{{ $organized }}</dd></div>
                    <div><dt>Record</dt><dd>{{ $record['wins'] }}–{{ $record['losses'] }}</dd></div>
                </dl>
            @endif
        </section>

        <section class="player-panel player-activity" aria-labelledby="activity-heading">
            <div class="section-heading"><h2 id="activity-heading">Open play activity</h2></div>
            @forelse($activity as $event)
                <a class="event-line" href="{{ route('open-play.show', $event) }}">
                    <span class="next-game-date"><strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('j') }}</strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('M') }}</span>
                    <span><strong>{{ $event->title }}</strong><span class="small muted">{{ $event->venueName() }} · {{ $event->host_id === $player->id ? 'Organized' : 'Joined' }}</span></span>
                </a>
            @empty
                <div class="player-empty"><x-icon name="calendar" size="24" /><p>{{ $player->show_activity || $isOwner ? 'No public open play activity yet.' : 'This player keeps their activity private.' }}</p></div>
            @endforelse
        </section>
    </div>
</div>
@endsection

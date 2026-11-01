@extends('layouts.public')
@section('title',$openPlay->title)
@if($openPlay->privacy === 'private' || $openPlay->status !== 'published')
@section('private','true')
@endif
@section('content')
@php
    $start = $openPlay->starts_at->setTimezone('Asia/Manila');
    $spotsLeft = max(0, $openPlay->capacity - $joined->count());
    $shareUrl = route('open-play.show', array_filter(['openPlay' => $openPlay, 'invite' => $openPlay->privacy === 'private' ? $openPlay->invite_token : null]));
@endphp
<div class="public-container player-area">
    <a class="back-link" href="{{ $isHost ? route('play.events') : route('open-play.index') }}"><x-icon name="left" size="14" />{{ $isHost ? 'My events' : 'Open play' }}</a>
    <header class="event-hero">
        <div>
            <div class="event-badges">
                @if($openPlay->status === 'draft')<span class="status draft">Draft · only you can see this</span>@elseif($openPlay->status === 'cancelled')<span class="status cancelled">Cancelled</span>@endif
                <span class="event-chip">{{ ['private' => 'Private · invite link', 'club' => 'Club members only'][$openPlay->privacy] ?? 'Public' }}</span>@if($openPlay->club)<a class="event-chip" href="{{ route('clubs.show', $openPlay->club) }}">{{ $openPlay->club->name }}</a>@endif
            </div>
            <h1>{{ $openPlay->title }}</h1>
            <p class="event-when"><x-icon name="calendar" size="16" />{{ $start->format('l, j F Y') }} · {{ $start->format('g:i A') }}–{{ $openPlay->ends_at->setTimezone('Asia/Manila')->format('g:i A') }}</p>
            <p class="event-when"><x-icon name="pin" size="16" />{{ $openPlay->venueName() }}@if($openPlay->business) · {{ $openPlay->business->city }}@endif</p>
        </div>
        <div class="event-host"><x-player-avatar :user="$openPlay->host" :size="44" /><span><small>Hosted by</small>@if($user && $openPlay->host->show_profile)<a href="{{ route('players.show', $openPlay->host) }}">{{ $openPlay->host->displayName() }}</a>@else<strong>{{ $openPlay->host->displayName() }}</strong>@endif</span></div>
    </header>

    <div class="event-layout">
        <div>
            <section class="player-panel">
                <dl class="event-facts">
                    <div><dt>Format</dt><dd>{{ $openPlay->formatLabel() }}</dd></div>
                    <div><dt>Players</dt><dd>{{ $openPlay->genderLabel() }}</dd></div>
                    <div><dt>Rating</dt><dd>{{ $openPlay->ratingRange() ?? 'All levels' }}</dd></div>
                    <div><dt>Fee</dt><dd>{{ $openPlay->fee_centavos > 0 ? '₱'.number_format($openPlay->fee_centavos / 100, 2).' per player' : 'Free' }}</dd></div>
                </dl>
                @if($openPlay->description)<div class="event-description">{!! nl2br(e($openPlay->description)) !!}</div>@endif
                @if($openPlay->fee_centavos > 0)<p class="event-fee-note"><x-icon name="receipt" size="16" /><span>{{ rtrim($openPlay->payment_note ?: 'Pay the host directly, as they instruct', '.') }}. Court desk does not collect open play fees.</span></p>@endif
            </section>

            <section class="player-panel" aria-labelledby="roster-heading">
                <div class="section-heading"><h2 id="roster-heading">Players <span class="subtle-count">{{ $joined->count() }} / {{ $openPlay->capacity }}</span></h2>@if($waitlist->isNotEmpty())<span class="small muted">{{ $waitlist->count() }} on waitlist</span>@endif</div>
                <div class="capacity-bar" role="img" aria-label="{{ $joined->count() }} of {{ $openPlay->capacity }} spots taken"><span style="width: {{ min(100, round($joined->count() / max(1, $openPlay->capacity) * 100)) }}%"></span></div>
                <ul class="roster">
                    @foreach($joined as $participant)
                        <li>
                            <x-player-avatar :user="$participant->user" :size="34" />
                            <span>@if($user && $participant->user->show_profile)<a href="{{ route('players.show', $participant->user) }}">{{ $participant->user->displayName() }}</a>@else{{ $participant->user->displayName() }}@endif @if($isHost && $participant->user->contact_number)<small>{{ $participant->user->contact_number }}</small>@endif</span>
                            @if($participant->user->show_rating)<span class="rating-chip small">{{ $participant->user->ratingBadge() }}</span>@endif
                        </li>
                    @endforeach
                </ul>
                @if($joined->isEmpty())<p class="small muted">No players yet. {{ $isHost ? 'Share the link to fill your spots.' : 'Be the first to join.' }}</p>@endif
                @if($isHost && $waitlist->isNotEmpty())<p class="small muted waitlist-names">Waitlist, in order: {{ $waitlist->map(fn ($entry) => $entry->user->displayName())->join(', ') }}</p>@endif
            </section>
        </div>

        <aside class="event-action">
            <div class="player-panel event-action-card">
                <p class="event-spots"><strong>{{ $spotsLeft }}</strong> {{ $spotsLeft === 1 ? 'spot' : 'spots' }} left</p>
                @if($isHost)
                    <p class="small muted">You are hosting. Players can join until the start time.</p>
                    @if($openPlay->status !== 'cancelled')
                        <a class="button full" href="{{ route('play.events.edit', $openPlay) }}">{{ $openPlay->status === 'draft' ? 'Edit and publish' : 'Edit event' }}</a>
                        @if($openPlay->status === 'published')
                            <label for="share-link" class="share-label">Share link</label>
                            <div class="share-row"><input id="share-link" value="{{ $shareUrl }}" readonly><button type="button" class="button secondary compact" data-copy="#share-link">Copy</button></div>
                            @if($openPlay->privacy === 'private')<p class="small muted">Anyone with this link can see the event.{{ $openPlay->passkey_hash ? ' Joining also needs your passkey.' : '' }}</p>@endif
                        @endif
                        <form method="post" action="{{ route('play.events.cancel', $openPlay) }}" data-confirm="Cancel this open play? Joined players will see it as cancelled.">@csrf @method('patch')<button class="text-button small danger-text">Cancel open play</button></form>
                    @endif
                @elseif(! $openPlay->isJoinable())
                    <p class="small muted">{{ $openPlay->status === 'cancelled' ? 'The host cancelled this open play.' : 'This open play has started or ended.' }}</p>
                @elseif(! $user)
                    <a class="button full" href="{{ route('login') }}">Sign in to join</a>
                    <p class="small muted">New here? <a class="text-link" href="{{ route('play.register') }}">Create a free player account</a>.</p>
                @elseif($entry && $entry->status !== 'left')
                    <p class="joined-state"><x-icon name="check" size="16" />{{ $entry->status === 'joined' ? 'You are in.' : 'You are on the waitlist.' }}</p>
                    <form method="post" action="{{ route('open-play.leave', $openPlay) }}">@csrf<input type="hidden" name="invite" value="{{ $invite }}"><button class="button secondary full">{{ $entry->status === 'joined' ? 'Leave open play' : 'Leave waitlist' }}</button></form>
                @elseif($problem)
                    <p class="eligibility-note">{{ $problem }}</p>
                    <a class="button secondary full" href="{{ route('play.profile') }}">Update profile</a>
                @else
                    <form method="post" action="{{ route('open-play.join', $openPlay) }}">@csrf<input type="hidden" name="invite" value="{{ $invite }}">
                        @if($openPlay->privacy === 'private' && $openPlay->passkey_hash)<label for="passkey">Passkey</label><input id="passkey" name="passkey" autocomplete="off" required maxlength="32" @error('passkey') aria-invalid="true" @enderror>@endif
                        <button class="button full">{{ $spotsLeft > 0 ? 'Join open play' : 'Join waitlist' }}<x-icon name="arrow" size="16" /></button>
                    </form>
                    @error('join')<p class="field-error">{{ $message }}</p>@enderror
                    <p class="small muted">Your contact number is shared with the host when you join.</p>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection

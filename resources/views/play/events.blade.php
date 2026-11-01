@extends('layouts.public')
@section('title','Your open plays')
@section('private','true')
@section('content')
<div class="public-container player-area">
    <header class="player-header">
        <div><h1>Your open plays</h1><p class="muted">Sessions you host and the ones you have joined.</p></div>
        <div class="player-header-actions"><a class="button secondary" href="{{ route('open-play.index') }}">Find open play</a><a class="button" href="{{ route('play.events.create') }}"><x-icon name="plus" size="16" />Host an open play</a></div>
    </header>
    <x-player-nav active="events" />

    <section class="player-panel" aria-labelledby="hosting-heading">
        <div class="section-heading"><h2 id="hosting-heading">Hosting <span class="subtle-count">{{ $hosting->count() }}</span></h2></div>
        @forelse($hosting as $event)
            <article class="event-row">
                <span class="next-game-date"><strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('j') }}</strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('M') }}</span>
                <div class="booking-card-body"><strong>{{ $event->title }}</strong><span>{{ $event->starts_at->setTimezone('Asia/Manila')->format('D · g:i A') }} · {{ $event->venueName() }}</span></div>
                <div class="booking-card-meta"><span class="status {{ $event->status === 'draft' ? 'draft' : 'confirmed' }}">{{ $event->status === 'draft' ? 'Draft' : 'Published' }}</span><span class="small muted">{{ $event->joined_count }} / {{ $event->capacity }} joined</span></div>
                <div class="booking-card-actions"><a class="text-link" href="{{ route('play.events.edit', $event) }}">Edit</a><a class="text-link" href="{{ route('open-play.show', $event) }}">View</a></div>
            </article>
        @empty
            <div class="player-empty"><x-icon name="users" size="26" /><h3>You are not hosting anything yet.</h3><p>Host an open play in four quick steps and share the link with your group.</p><a class="button secondary" href="{{ route('play.events.create') }}">Host an open play</a></div>
        @endforelse
    </section>

    <section class="player-panel" aria-labelledby="joined-heading">
        <div class="section-heading"><h2 id="joined-heading">Joined <span class="subtle-count">{{ $joined->count() }}</span></h2></div>
        @forelse($joined as $event)
            @php($myStatus = $event->participants->first()?->status)
            <article class="event-row">
                <span class="next-game-date"><strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('j') }}</strong>{{ $event->starts_at->setTimezone('Asia/Manila')->format('M') }}</span>
                <div class="booking-card-body"><strong>{{ $event->title }}</strong><span>{{ $event->starts_at->setTimezone('Asia/Manila')->format('D · g:i A') }} · {{ $event->venueName() }}</span></div>
                <div class="booking-card-meta"><span class="status {{ $event->status === 'cancelled' ? 'cancelled' : ($myStatus === 'joined' ? 'confirmed' : 'pending') }}">{{ $event->status === 'cancelled' ? 'Cancelled by host' : ($myStatus === 'joined' ? 'Joined' : 'Waitlisted') }}</span><span class="small muted">{{ $event->joined_count }} / {{ $event->capacity }} joined</span></div>
                <div class="booking-card-actions"><a class="text-link" href="{{ route('open-play.show', $event) }}">View</a></div>
            </article>
        @empty
            <p class="muted small">Open plays you join appear here. <a class="text-link" href="{{ route('open-play.index') }}">Browse open play</a></p>
        @endforelse
    </section>

    @if($past->isNotEmpty())
        <section class="player-panel" aria-labelledby="past-events-heading">
            <div class="section-heading"><h2 id="past-events-heading">Past open plays</h2></div>
            @foreach($past as $event)
                <a class="booking-line" href="{{ route('open-play.show', $event) }}"><span>{{ $event->starts_at->setTimezone('Asia/Manila')->format('D, j M Y') }}</span><span class="muted">{{ $event->title }} · {{ $event->venueName() }}</span><span class="small muted">{{ $event->host_id === auth()->id() ? 'Hosted' : 'Played' }}</span></a>
            @endforeach
        </section>
    @endif
</div>
@endsection

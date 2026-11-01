@extends('layouts.public')
@section('title','Your games')
@section('private','true')
@section('content')
<div class="public-container player-area">
    <header class="player-header">
        <div>
            <h1>Good to see you, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}.</h1>
            <p class="muted">Your next games, bookings and match record in one place.</p>
        </div>
        <div class="player-header-actions"><a class="button secondary" href="{{ route('play.scores.create') }}"><x-icon name="plus" size="16" />Record a score</a><a class="button" href="{{ route('public.venues.index') }}#venues">Book a court<x-icon name="arrow" size="16" /></a></div>
    </header>
    <x-player-nav active="dashboard" />

    @if($awaitingReview->isNotEmpty())
        <section class="player-panel review-callout" aria-labelledby="review-heading">
            <div class="section-heading"><h2 id="review-heading">Confirm these results</h2><span class="muted small">{{ $awaitingReview->count() }} waiting</span></div>
            <p class="small muted">An opponent recorded a match with you. Confirm it if the score is right, or dispute it if not. Only confirmed results count toward your record.</p>
            @foreach($awaitingReview as $match)<x-player-match :match="$match" :user="$user" />@endforeach
        </section>
    @endif

    <div class="player-grid">
        <section class="player-panel next-games" aria-labelledby="next-heading">
            <div class="section-heading"><h2 id="next-heading">Up next</h2><a class="text-link" href="{{ route('play.bookings') }}">All bookings<x-icon name="arrow" size="14" /></a></div>
            @forelse($upcoming as $booking)
                @if($loop->first)
                    <a class="next-game" href="{{ route('public.reservations.show', ['reference' => $booking->reference]) }}">
                        <span class="next-game-date"><strong>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('j') }}</strong>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('M') }}</span>
                        <span class="next-game-body"><strong>{{ $booking->business->name }}</strong><span>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('l · g:i A') }}–{{ $booking->ends_at->setTimezone('Asia/Manila')->format('g:i A') }} · {{ $booking->court->name }}@if($booking->coach) · Lesson with {{ $booking->coach->user->name }}@endif</span></span>
                        <x-player-booking-status :booking="$booking" />
                    </a>
                @else
                    <a class="booking-line" href="{{ route('public.reservations.show', ['reference' => $booking->reference]) }}"><span>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('D, j M · g:i A') }}</span><span class="muted">{{ $booking->business->name }} · {{ $booking->court->name }}</span><x-player-booking-status :booking="$booking" /></a>
                @endif
            @empty
                <div class="player-empty"><x-icon name="calendar" size="26" /><h3>No games booked yet.</h3><p>Find an open court and your reservation will show up here.</p><a class="button secondary" href="{{ route('public.venues.index') }}#venues">Find a court</a></div>
            @endforelse
        </section>

        <section class="player-panel record-card" aria-labelledby="record-heading">
            <div class="section-heading"><h2 id="record-heading">Your record</h2><a class="text-link" href="{{ route('play.scores.index') }}">Scores<x-icon name="arrow" size="14" /></a></div>
            @if($stats['played'] > 0)
                <p class="record-line"><strong>{{ $stats['wins'] }}</strong><span>–</span><strong>{{ $stats['losses'] }}</strong></p>
                <dl class="record-facts">
                    <div><dt>Win rate</dt><dd>{{ $stats['win_rate'] }}%</dd></div>
                    <div><dt>Current streak</dt><dd>{{ $stats['streak'] }} {{ $stats['streak_type'] }}</dd></div>
                    <div><dt>Confirmed matches</dt><dd>{{ $stats['played'] }}</dd></div>
                </dl>
            @else
                <p class="record-empty">Your record starts with your first confirmed match.</p>
                <p class="small muted">Record a score and add your opponent's account email. Once they confirm, it counts.</p>
            @endif
            <a class="button secondary full" href="{{ route('play.scores.create') }}"><x-icon name="plus" size="16" />Record a score</a>
        </section>
    </div>

    @if($recentMatches->isNotEmpty())
        <section class="player-panel" aria-labelledby="recent-heading">
            <div class="section-heading"><h2 id="recent-heading">Recent matches</h2><a class="text-link" href="{{ route('play.scores.index') }}">All scores<x-icon name="arrow" size="14" /></a></div>
            @foreach($recentMatches as $match)<x-player-match :match="$match" :user="$user" :actions="false" />@endforeach
        </section>
    @endif
</div>
@endsection

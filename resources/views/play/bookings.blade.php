@extends('layouts.public')
@section('title','Your bookings')
@section('private','true')
@section('content')
<div class="public-container player-area">
    <header class="player-header">
        <div><h1>Your bookings</h1><p class="muted">Court reservations and coaching lessons linked to your account. All times are Philippine time.</p></div>
        <div class="player-header-actions"><a class="button" href="{{ route('public.venues.index') }}#venues">Book a court<x-icon name="arrow" size="16" /></a></div>
    </header>
    <x-player-nav active="bookings" />

    <section class="player-panel" aria-labelledby="upcoming-heading">
        <div class="section-heading"><h2 id="upcoming-heading">Upcoming <span class="subtle-count">{{ $upcoming->count() }}</span></h2></div>
        @forelse($upcoming as $booking)
            <article class="booking-card">
                <span class="next-game-date"><strong>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('j') }}</strong>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('M') }}</span>
                <div class="booking-card-body">
                    <strong>{{ $booking->business->name }}</strong>
                    <span>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('D · g:i A') }}–{{ $booking->ends_at->setTimezone('Asia/Manila')->format('g:i A') }} · {{ $booking->court->name }}</span>
                    @if($booking->coach)<span class="small muted">Private lesson with {{ $booking->coach->user->name }}</span>@endif
                </div>
                <div class="booking-card-meta"><strong>₱{{ number_format($booking->total_centavos/100,2) }}</strong><x-player-booking-status :booking="$booking" /></div>
                <a class="button secondary compact" href="{{ route('public.reservations.show', ['reference' => $booking->reference]) }}">{{ in_array($booking->status, ['held'], true) && $booking->hold_expires_at?->isFuture() ? 'Pay & upload receipt' : 'View details' }}<x-icon name="right" size="14" /></a>
            </article>
        @empty
            <div class="player-empty"><x-icon name="calendar" size="26" /><h3>Nothing booked right now.</h3><p>Pick a venue and a time; your reservation will appear here while you are signed in.</p><a class="button secondary" href="{{ route('public.venues.index') }}#venues">Find a court</a></div>
        @endforelse
    </section>

    <section class="player-panel" aria-labelledby="past-heading">
        <div class="section-heading"><h2 id="past-heading">Past games <span class="subtle-count">{{ $past->total() }}</span></h2></div>
        @forelse($past as $booking)
            <article class="booking-card past">
                <span class="next-game-date"><strong>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('j') }}</strong>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('M') }}</span>
                <div class="booking-card-body">
                    <strong>{{ $booking->business->name }}</strong>
                    <span>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('D, Y · g:i A') }} · {{ $booking->court->name }}@if($booking->coach) · Lesson with {{ $booking->coach->user->name }}@endif</span>
                </div>
                <div class="booking-card-meta"><x-player-booking-status :booking="$booking" /></div>
                <div class="booking-card-actions">
                    @if(in_array($booking->status, ['confirmed', 'completed'], true))<a class="text-link" href="{{ route('play.scores.create', ['booking' => $booking->id]) }}">Record score</a>@endif
                    <a class="text-link" href="{{ route('public.venues.show', $booking->business) }}#availability">Book again</a>
                </div>
            </article>
        @empty
            <p class="muted small">Past games appear here after they are played.</p>
        @endforelse
        @if($past->hasPages())<nav class="simple-pagination" aria-label="Past booking pages">@if($past->previousPageUrl())<a class="button secondary" href="{{ $past->previousPageUrl() }}">Newer</a>@endif<span>Page {{ $past->currentPage() }} of {{ $past->lastPage() }}</span>@if($past->nextPageUrl())<a class="button secondary" href="{{ $past->nextPageUrl() }}">Older</a>@endif</nav>@endif
    </section>
</div>
@endsection

@extends('layouts.public')
@section('title','Record a score')
@section('private','true')
@section('content')
@php($format = old('format', 'singles'))
<div class="public-container player-area">
    <a class="back-link" href="{{ route('play.scores.index') }}"><x-icon name="left" size="14" />Your scores</a>
    <header class="player-header"><div><h1>Record a score</h1><p class="muted">Scores are entered from your side. Your opponent confirms the result before it counts.</p></div></header>

    <form method="post" action="{{ route('play.scores.store') }}" class="score-form" data-score-form>@csrf
        <section class="player-panel">
            <h2>The match</h2>
            <div class="score-fields two">
                <div><label for="played_on">Date played</label><input id="played_on" type="date" name="played_on" value="{{ old('played_on', $selectedBooking?->starts_at->setTimezone('Asia/Manila')->toDateString() ?? now('Asia/Manila')->toDateString()) }}" max="{{ now('Asia/Manila')->toDateString() }}" required @error('played_on') aria-invalid="true" @enderror></div>
                <fieldset class="format-switch"><legend>Format</legend>@foreach(['singles'=>'Singles','doubles'=>'Doubles'] as $value=>$label)<label><input type="radio" name="format" value="{{ $value }}" @checked($format===$value) data-format-input><span>{{ $label }}</span></label>@endforeach</fieldset>
            </div>
            <div class="score-fields two">
                <div><label for="booking_id">Linked booking <span class="muted">(optional)</span></label><select id="booking_id" name="booking_id"><option value="">Not from a booking</option>@foreach($bookings as $booking)<option value="{{ $booking->id }}" @selected((int) old('booking_id', $selectedBooking?->id) === $booking->id)>{{ $booking->starts_at->setTimezone('Asia/Manila')->format('j M · g:i A') }} · {{ $booking->business->name }}</option>@endforeach</select></div>
                <div><label for="location">Where <span class="muted">(if not a booking)</span></label><input id="location" name="location" value="{{ old('location') }}" maxlength="160" placeholder="Venue or court name"></div>
            </div>
        </section>

        <section class="player-panel">
            <h2>Players</h2>
            <p class="small muted">Adding a player's account email links the match to them. Without one, the match stays in your personal log and does not count toward your record.</p>
            <div class="score-sides">
                <fieldset class="score-side">
                    <legend>Your side</legend>
                    <p class="side-you">{{ auth()->user()->name }} <span class="muted small">(you)</span></p>
                    <div data-doubles-only @if($format!=='doubles') hidden @endif>
                        <label for="partner_name">Partner's name</label><input id="partner_name" name="partner_name" value="{{ old('partner_name') }}" maxlength="120">
                        <label for="partner_email">Partner's email <span class="muted">(optional)</span></label><input id="partner_email" type="email" name="partner_email" value="{{ old('partner_email') }}" maxlength="255" autocomplete="off">
                    </div>
                </fieldset>
                <fieldset class="score-side">
                    <legend>Opponents</legend>
                    <label for="opponent_name">Opponent's name</label><input id="opponent_name" name="opponent_name" value="{{ old('opponent_name') }}" required maxlength="120">
                    <label for="opponent_email">Opponent's email <span class="muted">(to confirm)</span></label><input id="opponent_email" type="email" name="opponent_email" value="{{ old('opponent_email') }}" maxlength="255" autocomplete="off">
                    <div data-doubles-only @if($format!=='doubles') hidden @endif>
                        <label for="second_opponent_name">Second opponent's name</label><input id="second_opponent_name" name="second_opponent_name" value="{{ old('second_opponent_name') }}" maxlength="120">
                        <label for="second_opponent_email">Second opponent's email <span class="muted">(optional)</span></label><input id="second_opponent_email" type="email" name="second_opponent_email" value="{{ old('second_opponent_email') }}" maxlength="255" autocomplete="off">
                    </div>
                </fieldset>
            </div>
        </section>

        <section class="player-panel">
            <h2>Games</h2>
            <p class="small muted">Enter each game's points. Leave unused games empty.</p>
            <div class="games-grid" role="group" aria-label="Game scores">
                <span></span><span class="games-head">You</span><span class="games-head">Them</span>
                @for($game = 0; $game < 5; $game++)
                    <span class="games-label">Game {{ $game + 1 }}</span>
                    <input type="number" inputmode="numeric" min="0" max="99" name="games[{{ $game }}][mine]" value="{{ old("games.$game.mine") }}" aria-label="Game {{ $game + 1 }}, your points" @if($game===0) required @endif>
                    <input type="number" inputmode="numeric" min="0" max="99" name="games[{{ $game }}][theirs]" value="{{ old("games.$game.theirs") }}" aria-label="Game {{ $game + 1 }}, their points" @if($game===0) required @endif>
                @endfor
            </div>
        </section>

        <div class="form-actions"><button class="button">Save score<x-icon name="arrow" size="16" /></button><a class="text-link" href="{{ route('play.scores.index') }}">Cancel</a></div>
    </form>
</div>
@endsection

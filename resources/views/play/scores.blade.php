@extends('layouts.public')
@section('title','Your scores')
@section('private','true')
@section('content')
<div class="public-container player-area">
    <header class="player-header">
        <div><h1>Your scores</h1><p class="muted">Every match you log. Only results confirmed by an opponent count toward your record.</p></div>
        <div class="player-header-actions"><a class="button" href="{{ route('play.scores.create') }}"><x-icon name="plus" size="16" />Record a score</a></div>
    </header>
    <x-player-nav active="scores" />

    <section class="stat-strip" aria-label="Confirmed record">
        <div><span>Record</span><strong>{{ $stats['wins'] }}–{{ $stats['losses'] }}</strong></div>
        <div><span>Win rate</span><strong>{{ $stats['win_rate'] === null ? '—' : $stats['win_rate'].'%' }}</strong></div>
        <div><span>Current streak</span><strong>{{ $stats['streak'] ? $stats['streak'].' '.$stats['streak_type'] : '—' }}</strong></div>
        <div><span>Matches logged</span><strong>{{ $stats['logged'] }}</strong></div>
    </section>

    <section class="player-panel" aria-labelledby="matches-heading">
        <div class="section-heading"><h2 id="matches-heading">Match log</h2></div>
        @forelse($matches as $match)
            <x-player-match :match="$match" :user="$user" />
        @empty
            <div class="player-empty"><x-icon name="list" size="26" /><h3>No matches logged yet.</h3><p>Record your first score. Add your opponent's account email so they can confirm it.</p><a class="button secondary" href="{{ route('play.scores.create') }}">Record a score</a></div>
        @endforelse
    </section>
</div>
@endsection

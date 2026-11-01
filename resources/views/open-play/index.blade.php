@extends('layouts.public')
@section('title','Open play')
@section('content')
<div class="public-container player-area">
    <header class="player-header">
        <div><h1>Open play near you.</h1><p class="muted">Drop-in sessions hosted by players. Take a spot, meet new partners, play more games.</p></div>
        <div class="player-header-actions"><a class="button" href="{{ route('play.events.create') }}"><x-icon name="plus" size="16" />Host an open play</a></div>
    </header>
    <form method="get" action="{{ route('open-play.index') }}" class="open-play-filters">
        <div><label for="search">Search</label><input id="search" name="search" value="{{ request('search') }}" maxlength="120" placeholder="City, venue or title"></div>
        <div><label for="date">Date</label><input id="date" type="date" name="date" value="{{ request('date') }}" min="{{ now('Asia/Manila')->toDateString() }}"></div>
        <div><label for="format">Format</label><select id="format" name="format"><option value="">Any format</option>@foreach(['doubles' => 'Doubles', 'singles' => 'Singles', 'mixed_rotation' => 'Rotation'] as $value => $label)<option value="{{ $value }}" @selected(request('format') === $value)>{{ $label }}</option>@endforeach</select></div>
        <button class="button secondary">Search</button>
        @if(request()->hasAny(['search', 'date', 'format']))<a class="text-link" href="{{ route('open-play.index') }}">Clear</a>@endif
    </form>
    <div class="open-play-grid">
        @forelse($events as $event)
            <x-open-play-card :event="$event" />
        @empty
            <div class="player-empty open-play-empty"><x-icon name="users" size="28" /><h3>{{ request()->hasAny(['search', 'date', 'format']) ? 'No open plays match these filters.' : 'No open plays scheduled yet.' }}</h3><p>Be the first to host one. Set the time, place and spots, then share the link.</p><a class="button secondary" href="{{ route('play.events.create') }}">Host an open play</a></div>
        @endforelse
    </div>
    @if($events->hasPages())<nav class="simple-pagination" aria-label="Open play pages">@if($events->previousPageUrl())<a class="button secondary" href="{{ $events->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $events->currentPage() }} of {{ $events->lastPage() }}</span>@if($events->nextPageUrl())<a class="button secondary" href="{{ $events->nextPageUrl() }}">Next</a>@endif</nav>@endif
</div>
@endsection

@extends('layouts.public')
@section('title','Clubs')
@section('content')
<div class="public-container player-area">
    <header class="player-header">
        <div><h1>Clubs</h1><p class="muted">Find your pickleball community. Follow its feed, meet members and join club open play.</p></div>
        <div class="player-header-actions"><a class="button" href="{{ route('clubs.create') }}"><x-icon name="plus" size="16" />Create a club</a></div>
    </header>

    <div class="clubs-toolbar">
        @auth
            <nav class="player-nav clubs-tabs" aria-label="Club lists">
                <a href="{{ route('clubs.index', ['tab' => 'mine']) }}" @if($tab === 'mine') aria-current="page" @endif><x-icon name="users" size="16" />My clubs</a>
                <a href="{{ route('clubs.index', ['tab' => 'discover']) }}" @if($tab === 'discover') aria-current="page" @endif><x-icon name="search" size="16" />Discover</a>
            </nav>
        @endauth
        <form method="get" action="{{ route('clubs.index') }}" class="clubs-search" role="search">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <label for="club-search" class="sr-only">Search clubs</label>
            <x-icon name="search" size="16" />
            <input id="club-search" name="search" value="{{ request('search') }}" maxlength="80" placeholder="Search by club name or city">
            <button class="button secondary compact">Search</button>
        </form>
    </div>

    @if($clubs->isEmpty())
        <div class="player-empty open-play-empty">
            <x-icon name="users" size="28" />
            @if(request('search'))
                <h3>No clubs match “{{ request('search') }}”.</h3><p>Try another name or city.</p><a class="button secondary" href="{{ route('clubs.index', ['tab' => $tab]) }}">Clear search</a>
            @elseif($tab === 'mine')
                <h3>You are not in any clubs yet.</h3><p>Discover a community near you, or start your own.</p><a class="button secondary" href="{{ route('clubs.index', ['tab' => 'discover']) }}">Discover clubs</a>
            @else
                <h3>No public clubs yet.</h3><p>Start the first one. Clubs get a feed, a member roster and their own open play.</p><a class="button secondary" href="{{ route('clubs.create') }}">Create a club</a>
            @endif
        </div>
    @else
        <div class="club-grid">
            @foreach($clubs as $club)
                <a class="club-tile" href="{{ route('clubs.show', $club) }}">
                    <x-club-logo :club="$club" :size="120" />
                    <strong>{{ $club->name }}</strong>
                    <span class="small muted">{{ $club->locationLabel() ?? 'Location not set' }}</span>
                    <span class="club-tile-meta">
                        <span>{{ $club->active_memberships_count }} {{ $club->active_memberships_count === 1 ? 'member' : 'members' }}</span>
                        @if(in_array($club->id, $pendingIds, true))<span class="status pending">Requested</span>@elseif(! $club->is_public)<span class="event-chip">Private</span>@endif
                    </span>
                </a>
            @endforeach
        </div>
        @if(method_exists($clubs, 'hasPages') && $clubs->hasPages())<nav class="simple-pagination" aria-label="Club pages">@if($clubs->previousPageUrl())<a class="button secondary" href="{{ $clubs->previousPageUrl() }}">Previous</a>@endif<span>Page {{ $clubs->currentPage() }} of {{ $clubs->lastPage() }}</span>@if($clubs->nextPageUrl())<a class="button secondary" href="{{ $clubs->nextPageUrl() }}">Next</a>@endif</nav>@endif
    @endif
</div>
@endsection

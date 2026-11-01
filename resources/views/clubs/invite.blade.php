@extends('layouts.public')
@section('title','Join '.$club->name)
@section('private','true')
@section('content')
<div class="public-container player-area">
    <section class="club-invite">
        <x-club-logo :club="$club" :size="104" />
        <p class="small muted">You're invited to join</p>
        <h1>{{ $club->name }}</h1>
        <p class="club-header-meta">@if($club->locationLabel())<span><x-icon name="pin" size="14" />{{ $club->locationLabel() }}</span>@endif<span><x-icon name="users" size="14" />{{ $club->active_memberships_count }} {{ $club->active_memberships_count === 1 ? 'member' : 'members' }}</span></p>
        @if($club->description)<p class="club-invite-description">{{ \Illuminate\Support\Str::limit($club->description, 220) }}</p>@endif
        @auth
            <form method="post" action="{{ route('clubs.invite.accept', $token) }}">@csrf<button class="button">Join club<x-icon name="arrow" size="16" /></button></form>
        @else
            <a class="button" href="{{ route('login') }}">Sign in to join</a>
            <p class="small muted">New here? <a class="text-link" href="{{ route('register') }}">Create a free account</a> and you'll come right back here.</p>
        @endauth
    </section>
</div>
@endsection

@extends('layouts.public')
@section('title','Your profile')
@section('private','true')
@section('content')
<div class="public-container player-area">
    <header class="player-header"><div><h1>Your profile</h1><p class="muted">How you appear to venues and the players you record matches with.</p></div></header>
    <x-player-nav active="profile" />
    <form method="post" action="{{ route('play.profile.update') }}" class="player-panel profile-form">@csrf @method('put')
        <label for="name">Full name</label><input id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required maxlength="120">
        <label for="email">Email</label><input id="email" value="{{ $user->email }}" disabled><small>Your sign-in email. Opponents use it to link matches to you.</small>
        <div class="score-fields two">
            <div><label for="skill_level">Skill level <span class="muted">(self-rated)</span></label><select id="skill_level" name="skill_level"><option value="">Not set</option>@foreach(['2.0','2.5','3.0','3.5','4.0','4.5','5.0'] as $level)<option value="{{ $level }}" @selected(old('skill_level', $user->skill_level)===$level)>{{ $level }}</option>@endforeach</select></div>
            <div><label for="home_city">Home city</label><input id="home_city" name="home_city" value="{{ old('home_city', $user->home_city) }}" maxlength="120" placeholder="e.g. Quezon City"></div>
        </div>
        <div class="form-actions"><button class="button">Save profile</button></div>
    </form>
</div>
@endsection

@extends('layouts.public')
@section('title','Reset your password')
@section('private','true')
@section('error_heading','Please check your email address.')
@section('content')<div class="public-container coach-registration player-registration"><a class="back-link" href="{{ route('login') }}"><x-icon name="left" size="15" />Sign in</a><h1>Forgot your password?</h1><p class="muted">Enter your account email and we'll send you a link to choose a new one.</p><form class="panel" method="post" action="{{ route('password.email') }}">@csrf<label for="email">Email</label><input type="email" id="email" name="email" autocomplete="username" value="{{ old('email') }}" required maxlength="255"><div class="form-actions"><button class="button">Send reset link</button></div></form></div>@endsection

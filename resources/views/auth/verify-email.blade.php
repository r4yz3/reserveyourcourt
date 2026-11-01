@extends('layouts.public')
@section('title','Confirm your email')
@section('private','true')
@section('content')<div class="public-container coach-registration player-registration"><a class="back-link" href="{{ route('play.dashboard') }}"><x-icon name="left" size="15" />Your games</a><h1>Confirm your email to continue.</h1><p class="muted">We need to know this address is yours before you list a venue. Open the link we sent to <strong>{{ auth()->user()->email }}</strong>.</p><form class="panel" method="post" action="{{ route('verification.send') }}">@csrf<p class="small muted">Can't find it? Check your spam folder or send a new link.</p><div class="form-actions"><button class="button">Send a new link</button></div></form></div>@endsection

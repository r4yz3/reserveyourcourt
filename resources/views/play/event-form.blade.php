@extends('layouts.public')
@section('title', $openPlay->exists ? 'Edit open play' : 'New open play')
@section('private','true')
@section('content')
@php
    $start = $openPlay->starts_at?->setTimezone('Asia/Manila');
    $duration = $openPlay->exists ? (int) $openPlay->starts_at->diffInMinutes($openPlay->ends_at) : 120;
    $steps = ['basics' => ['Basics', 'list'], 'where' => ['When & where', 'calendar'], 'rules' => ['Capacity & rules', 'settings'], 'fee' => ['Event fee', 'coin']];
    $stepErrors = [
        'basics' => ['title', 'description', 'privacy', 'passkey'],
        'where' => ['date', 'start_time', 'duration', 'business_id', 'location'],
        'rules' => ['capacity', 'format', 'gender_rule', 'min_rating', 'max_rating'],
        'fee' => ['fee', 'payment_note'],
    ];
    $firstErrorStep = collect($stepErrors)->search(fn (array $fields) => $errors->hasAny($fields)) ?: 'basics';
    $privacy = old('privacy', $openPlay->privacy);
@endphp
<div class="public-container player-area">
    <a class="back-link" href="{{ route('play.events') }}"><x-icon name="left" size="14" />My events</a>
    <header class="player-header">
        <div><h1>{{ $openPlay->exists ? 'Edit open play' : 'Host an open play' }}</h1><p class="muted">Set the details, then save a draft or publish so players can find and join it.</p></div>
    </header>

    <form method="post" action="{{ $openPlay->exists ? route('play.events.update', $openPlay) : route('play.events.store') }}" class="event-wizard" data-wizard data-start-step="{{ $firstErrorStep }}" novalidate>
        @csrf @if($openPlay->exists) @method('put') @endif
        <nav class="wizard-steps" aria-label="Open play steps">
            @foreach($steps as $key => [$label, $icon])
                <button type="button" class="wizard-step" data-step-target="{{ $key }}" aria-controls="step-{{ $key }}"><span class="wizard-step-index">{{ $loop->iteration }}</span><x-icon :name="$icon" size="16" /><span class="wizard-step-label">{{ $label }}</span>@if($errors->hasAny($stepErrors[$key]))<span class="wizard-step-error" aria-label="has errors">!</span>@endif</button>
            @endforeach
        </nav>

        <section class="player-panel wizard-panel" id="step-basics" data-step="basics">
            <div class="panel-intro"><h2>Basics</h2><p>What players see in discovery and on the event page.</p></div>
            <label for="title">Title</label>
            <input id="title" name="title" value="{{ old('title', $openPlay->title) }}" maxlength="120" required placeholder="Sunday morning open play" @error('title') aria-invalid="true" @enderror>
            @error('title')<p class="field-error">{{ $message }}</p>@enderror
            <label for="description">Description <span class="muted">(optional)</span></label>
            <textarea id="description" name="description" rows="5" maxlength="2000" placeholder="Skill notes, ball type, what to bring, parking…">{{ old('description', $openPlay->description) }}</textarea>
            <fieldset class="privacy-options">
                <legend>Privacy</legend>
                <label class="privacy-option"><input type="radio" name="privacy" value="public" @checked($privacy === 'public') data-privacy-input><span><x-icon name="eye" size="18" /><strong>Public</strong><small>Listed in open play discovery. Any signed-in player can join.</small></span></label>
                <label class="privacy-option"><input type="radio" name="privacy" value="private" @checked($privacy === 'private') data-privacy-input><span><x-icon name="shield" size="18" /><strong>Private</strong><small>Unlisted. Only people with your invite link can see it, with an optional passkey to join.</small></span></label>
            </fieldset>
            <div class="passkey-field" data-private-only @if($privacy !== 'private') hidden @endif>
                <label for="passkey">Passkey <span class="muted">(optional)</span></label>
                <input id="passkey" name="passkey" maxlength="32" autocomplete="off" placeholder="{{ $openPlay->passkey_hash ? 'Leave blank to keep the current passkey' : 'At least 4 characters' }}" @error('passkey') aria-invalid="true" @enderror>
                @error('passkey')<p class="field-error">{{ $message }}</p>@enderror
                @if($openPlay->passkey_hash)<label class="inline-check"><input type="checkbox" name="remove_passkey" value="1"> Remove the passkey</label>@endif
            </div>
        </section>

        <section class="player-panel wizard-panel" id="step-where" data-step="where">
            <div class="panel-intro"><h2>When &amp; where</h2><p>All times are Philippine time. Make sure the court is arranged with the venue; open play does not reserve a court.</p></div>
            <div class="score-fields three">
                <div><label for="date">Date</label><input id="date" type="date" name="date" value="{{ old('date', $start?->toDateString() ?? now('Asia/Manila')->addDay()->toDateString()) }}" min="{{ now('Asia/Manila')->toDateString() }}" required @error('date') aria-invalid="true" @enderror></div>
                <div><label for="start_time">Start time</label><input id="start_time" type="time" name="start_time" step="1800" value="{{ old('start_time', $start?->format('H:i') ?? '08:00') }}" required></div>
                <div><label for="duration">Duration</label><select id="duration" name="duration">@foreach([60 => '1 hour', 90 => '1.5 hours', 120 => '2 hours', 150 => '2.5 hours', 180 => '3 hours', 240 => '4 hours', 300 => '5 hours', 360 => '6 hours'] as $minutes => $label)<option value="{{ $minutes }}" @selected((int) old('duration', $duration) === $minutes)>{{ $label }}</option>@endforeach</select></div>
            </div>
            @error('date')<p class="field-error">{{ $message }}</p>@enderror
            <label for="business_id">Venue</label>
            <select id="business_id" name="business_id" data-venue-select><option value="">Somewhere else (enter below)</option>@foreach($venues as $venue)<option value="{{ $venue->id }}" @selected((int) old('business_id', $openPlay->business_id) === $venue->id)>{{ $venue->name }} · {{ $venue->city }}</option>@endforeach</select>
            @error('business_id')<p class="field-error">{{ $message }}</p>@enderror
            <div data-location-field>
                <label for="location">Location</label>
                <input id="location" name="location" value="{{ old('location', $openPlay->location) }}" maxlength="160" placeholder="Court name, barangay or landmark" @error('location') aria-invalid="true" @enderror>
                @error('location')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="player-panel wizard-panel" id="step-rules" data-step="rules">
            <div class="panel-intro"><h2>Capacity &amp; rules</h2><p>Players outside these rules see why they cannot join. Extra players join a waitlist.</p></div>
            <div class="score-fields two">
                <div><label for="capacity">Player spots</label><input id="capacity" type="number" name="capacity" min="2" max="64" value="{{ old('capacity', $openPlay->capacity) }}" required @error('capacity') aria-invalid="true" @enderror><small>Not counting you as host.</small>@error('capacity')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label for="format">Format</label><select id="format" name="format">@foreach(['doubles' => 'Doubles', 'singles' => 'Singles', 'mixed_rotation' => 'Rotation (paddle stack)'] as $value => $label)<option value="{{ $value }}" @selected(old('format', $openPlay->format) === $value)>{{ $label }}</option>@endforeach</select></div>
            </div>
            <fieldset class="choice-row">
                <legend>Who can join</legend>
                @foreach(['any' => 'Open to all', 'mixed' => 'Mixed', 'men' => "Men's", 'women' => "Women's"] as $value => $label)
                    <label class="choice"><input type="radio" name="gender_rule" value="{{ $value }}" @checked(old('gender_rule', $openPlay->gender_rule) === $value)><span>{{ $label }}</span></label>
                @endforeach
            </fieldset>
            <div class="score-fields two">
                <div><label for="min_rating">Lowest self rating <span class="muted">(optional)</span></label><input id="min_rating" type="number" name="min_rating" min="2" max="5.5" step="0.05" value="{{ old('min_rating', $openPlay->min_rating) }}" placeholder="Any"></div>
                <div><label for="max_rating">Highest self rating <span class="muted">(optional)</span></label><input id="max_rating" type="number" name="max_rating" min="2" max="5.5" step="0.05" value="{{ old('max_rating', $openPlay->max_rating) }}" placeholder="Any" @error('max_rating') aria-invalid="true" @enderror></div>
            </div>
            @error('max_rating')<p class="field-error">{{ $message }}</p>@enderror
            <small>Setting a range requires players to have a self rating on their profile.</small>
        </section>

        <section class="player-panel wizard-panel" id="step-fee" data-step="fee">
            <div class="panel-intro"><h2>Event fee</h2><p>Optional. Players pay you directly; Court desk does not collect open play fees.</p></div>
            <label for="fee">Fee per player · PHP</label>
            <div class="money-input"><span>₱</span><input id="fee" type="number" name="fee" min="0" max="10000" step="0.01" value="{{ old('fee', $openPlay->fee_centavos ? number_format($openPlay->fee_centavos / 100, 2, '.', '') : '') }}" placeholder="0.00 for free"></div>
            @error('fee')<p class="field-error">{{ $message }}</p>@enderror
            <label for="payment_note">How players pay <span class="muted">(optional)</span></label>
            <input id="payment_note" name="payment_note" value="{{ old('payment_note', $openPlay->payment_note) }}" maxlength="255" placeholder="Cash at the court, or GCash to the host">
            <div class="wizard-summary" aria-live="polite">
                <h3>Before you publish</h3>
                <p class="small muted">Publishing lists a public open play in discovery right away. Private open plays are only visible through your link. You can edit details later; capacity cannot drop below players already joined.</p>
            </div>
        </section>

        <footer class="wizard-bar">
            <span class="small muted" data-wizard-progress>All steps</span>
            <div class="wizard-actions">
                <button type="button" class="button secondary" data-wizard-back hidden><x-icon name="left" size="16" />Back</button>
                <button type="button" class="button" data-wizard-next hidden>Continue<x-icon name="arrow" size="16" /></button>
                @if(! $openPlay->exists || $openPlay->status === 'draft')<button class="button secondary" name="intent" value="draft" data-wizard-final>Save draft</button>@endif
                <button class="button" name="intent" value="publish" data-wizard-final>{{ $openPlay->status === 'published' ? 'Save changes' : 'Publish open play' }}</button>
            </div>
        </footer>
    </form>
</div>
@endsection

@extends('layouts.public')
@section('title','Your profile')
@section('private','true')
@section('content')
<div class="public-container player-area">
    <header class="player-header">
        <div><h1>Your profile</h1><p class="muted">Your player card and the private details hosts use to reach you.</p></div>
        <div class="player-header-actions"><a class="button secondary" href="{{ route('players.show', $user) }}"><x-icon name="eye" size="16" />View public profile</a></div>
    </header>
    <x-player-nav active="profile" />

    <div class="profile-layout">
        <aside class="profile-aside">
            <section class="player-panel profile-photo-card">
                <x-player-avatar :user="$user" :size="96" />
                <h2>{{ $user->displayName() }}</h2>
                <p class="small muted">{{ $user->email }}</p>
                <span class="rating-chip">{{ $user->ratingBadge() }}</span>
                <form method="post" action="{{ route('play.profile.photo') }}" enctype="multipart/form-data" class="photo-upload">@csrf
                    <label class="button secondary compact" for="photo"><x-icon name="plus" size="14" />{{ $user->photo_path ? 'Change photo' : 'Upload photo' }}</label>
                    <input id="photo" class="sr-only" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-auto-submit-file>
                    <noscript><button class="button compact">Upload</button></noscript>
                </form>
                @if($user->photo_path)<form method="post" action="{{ route('play.profile.photo.destroy') }}">@csrf @method('delete')<button class="text-button small">Remove photo</button></form>@endif
                <p class="small muted">JPEG, PNG or WebP. Resized to 600&nbsp;px and stored as WebP.</p>
                @error('photo')<p class="field-error">{{ $message }}</p>@enderror
            </section>
            @if($user->isProfileIncomplete())
                <section class="player-panel profile-checklist">
                    <h2>Finish for open play</h2>
                    <ul>
                        <li class="{{ filled($user->gender) ? 'done' : '' }}"><x-icon name="check" size="14" />Gender</li>
                        <li class="{{ filled($user->contact_number) ? 'done' : '' }}"><x-icon name="check" size="14" />Contact number</li>
                        <li class="{{ filled($user->home_city) ? 'done' : '' }}"><x-icon name="check" size="14" />City</li>
                    </ul>
                </section>
            @endif
        </aside>

        <form method="post" action="{{ route('play.profile.update') }}" class="profile-form-stack">@csrf @method('put')
            <section class="player-panel">
                <div class="panel-intro"><h2>Identity</h2><p>How you appear to other players.</p></div>
                <label for="name">Full name</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required maxlength="120">
                <small>Kept on your account. Other players see your alias when you set one.</small>
                <label for="alias">Alias or nickname <span class="muted">(optional)</span></label>
                <input id="alias" name="alias" value="{{ old('alias', $user->alias) }}" maxlength="40" placeholder="Shown publicly instead of your full name">
                <fieldset class="choice-row">
                    <legend>Gender</legend>
                    @foreach(['' => 'Prefer not to say', 'male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                        <label class="choice"><input type="radio" name="gender" value="{{ $value }}" @checked((string) old('gender', $user->gender) === $value)><span>{{ $label }}</span></label>
                    @endforeach
                </fieldset>
                <small>Used only for men's and women's open play eligibility.</small>
                <label for="bio">Bio <span class="muted">(optional)</span></label>
                <textarea id="bio" name="bio" rows="3" maxlength="500" placeholder="A short intro for your player card" data-counter="bio-count">{{ old('bio', $user->bio) }}</textarea>
                <small><span id="bio-count">{{ mb_strlen((string) old('bio', $user->bio)) }}</span>/500</small>
            </section>

            <section class="player-panel">
                <div class="panel-intro"><h2>Self rating</h2><p>Your honest skill estimate. Hosts use it for rating-ranged open play. Leave as not rated if unsure.</p></div>
                <label for="self_rating" class="sr-only">Self rating</label>
                <x-rating-input :value="old('self_rating', $user->self_rating)" />
            </section>

            <section class="player-panel">
                <div class="panel-intro"><h2>Contact <span class="private-tag"><x-icon name="shield" size="13" />Private</span></h2><p>Never shown on your public profile. Hosts of open plays you join can see your contact number.</p></div>
                <label for="sign_in_email">Sign-in email</label>
                <input id="sign_in_email" value="{{ $user->email }}" disabled>
                <label for="contact_number">Contact number</label>
                <input id="contact_number" name="contact_number" type="tel" autocomplete="tel" value="{{ old('contact_number', $user->contact_number) }}" maxlength="30" placeholder="+63 9XX XXX XXXX">
            </section>

            <section class="player-panel">
                <div class="panel-intro"><h2>Location</h2><p>City and country can appear on your player card. Your address stays private.</p></div>
                <div class="score-fields two">
                    <div><label for="country">Country</label><select id="country" name="country">@foreach($countries as $country)<option @selected(old('country', $user->country) === $country)>{{ $country }}</option>@endforeach</select></div>
                    <div><label for="home_city">City</label><input id="home_city" name="home_city" list="ph-cities" value="{{ old('home_city', $user->home_city) }}" maxlength="120" placeholder="Search a city or municipality"></div>
                </div>
                <label for="address">Address <span class="muted">(optional, private)</span></label>
                <textarea id="address" name="address" rows="2" maxlength="255" placeholder="Street, barangay, landmarks">{{ old('address', $user->address) }}</textarea>
                <x-city-options />
            </section>

            <section class="player-panel">
                <div class="panel-intro"><h2>Profile visibility</h2><p>Choose what signed-in players see on your player card.</p></div>
                <div class="toggle-list">
                    @foreach(['show_profile' => ['Public player card', 'Other players can open your card from rosters and match logs.'], 'show_location' => ['Location', 'City and country on your card.'], 'show_rating' => ['Self rating', 'Your rating badge, or NR when not rated.'], 'show_activity' => ['Open play activity', 'Public open plays you host or join.'], 'show_stats' => ['Stats', 'Open plays played, organized and your confirmed match record.']] as $field => [$label, $help])
                        <label class="toggle-row"><span><strong>{{ $label }}</strong><small>{{ $help }}</small></span><input type="checkbox" role="switch" name="{{ $field }}" value="1" @checked(old($field, $user->$field))></label>
                    @endforeach
                </div>
            </section>

            <div class="form-actions sticky-actions"><button class="button"><x-icon name="check" size="16" />Save profile</button></div>
        </form>
    </div>
</div>
@endsection

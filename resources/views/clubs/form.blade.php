@extends('layouts.public')
@section('title', $club->exists ? 'Club settings' : 'Create a club')
@section('private','true')
@section('content')
<div class="public-container player-area club-form-page">
    <a class="back-link" href="{{ $club->exists ? route('clubs.show', $club) : route('clubs.index') }}"><x-icon name="left" size="14" />{{ $club->exists ? $club->name : 'Clubs' }}</a>
    <header class="player-header"><div><h1>{{ $club->exists ? 'Club settings' : 'Create a club' }}</h1><p class="muted">Clubs get a feed, a member roster and their own open play schedule.</p></div></header>

    @if($club->exists)
        <section class="player-panel club-media-editor" aria-labelledby="media-heading">
            <div class="panel-intro"><h2 id="media-heading">Logo &amp; cover</h2><p>Square logos work best. Covers display wide, about 4:1. Images are resized and stored as WebP.</p></div>
            <div class="club-media-preview">
                <div class="club-cover {{ $club->cover_path ? '' : 'no-cover' }}">@if($club->cover_path)<img src="{{ route('clubs.media', ['club' => $club, 'kind' => 'cover']) }}?v={{ substr(md5($club->cover_path), 0, 8) }}" alt="">@endif</div>
                <x-club-logo :club="$club" :size="88" class="club-media-logo" />
            </div>
            <div class="club-media-actions">
                @foreach(['logo' => 'logo', 'cover' => 'cover image'] as $kind => $label)
                    <form method="post" action="{{ route('clubs.media.store', ['club' => $club, 'kind' => $kind]) }}" enctype="multipart/form-data">@csrf
                        <label class="button secondary compact" for="{{ $kind }}-file"><x-icon name="plus" size="14" />{{ $club->{$kind.'_path'} ? 'Replace '.$label : 'Upload '.$label }}</label>
                        <input id="{{ $kind }}-file" class="sr-only" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-auto-submit-file>
                        <noscript><button class="button compact">Upload</button></noscript>
                    </form>
                @endforeach
            </div>
            @error('image')<p class="field-error">{{ $message }}</p>@enderror
        </section>
    @endif

    <form method="post" action="{{ $club->exists ? route('clubs.update', $club) : route('clubs.store') }}">@csrf @if($club->exists) @method('put') @endif
        <section class="player-panel">
            <div class="panel-intro"><h2>About the club</h2><p>What players see in Discover and on your club page.</p></div>
            <label for="name">Club name</label>
            <input id="name" name="name" value="{{ old('name', $club->name) }}" required minlength="3" maxlength="80" placeholder="Sunday Dinkers" @error('name') aria-invalid="true" @enderror>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
            <label for="description">Description <span class="muted">(optional)</span></label>
            <textarea id="description" name="description" rows="5" maxlength="3000" placeholder="Who you are, where and when you play, and how to join.">{{ old('description', $club->description) }}</textarea>
            <div class="score-fields two">
                <div><label for="country">Country</label><select id="country" name="country">@foreach($countries as $country)<option @selected(old('country', $club->country) === $country)>{{ $country }}</option>@endforeach</select></div>
                <div><label for="city">City or area</label><input id="city" name="city" list="ph-cities" value="{{ old('city', $club->city) }}" maxlength="120" placeholder="Search a city or municipality"></div>
            </div>
            <x-city-options />
        </section>

        <section class="player-panel">
            <div class="panel-intro"><h2>Links <span class="muted small">(optional)</span></h2><p>Shown on the About tab.</p></div>
            <div class="score-fields two">
                <div><label for="facebook_url">Facebook page</label><input id="facebook_url" type="url" name="facebook_url" value="{{ old('facebook_url', $club->facebook_url) }}" maxlength="255" placeholder="https://facebook.com/yourclub" @error('facebook_url') aria-invalid="true" @enderror>@error('facebook_url')<p class="field-error">{{ $message }}</p>@enderror</div>
                <div><label for="website_url">Website</label><input id="website_url" type="url" name="website_url" value="{{ old('website_url', $club->website_url) }}" maxlength="255" placeholder="https://" @error('website_url') aria-invalid="true" @enderror>@error('website_url')<p class="field-error">{{ $message }}</p>@enderror</div>
            </div>
        </section>

        <section class="player-panel">
            <div class="panel-intro"><h2>Access</h2><p>Change these any time.</p></div>
            <div class="toggle-list">
                @foreach(['is_public' => ['Public club', 'Listed in Discover. Anyone can join or request to join. Private clubs are joined only through your invite link.'], 'auto_approve' => ['Auto-approve members', 'Join requests are accepted immediately. Turn off to review each request.'], 'members_can_post' => ['Members can post', 'When off, only the owner and moderators post to the feed.']] as $field => [$label, $help])
                    <label class="toggle-row"><span><strong>{{ $label }}</strong><small>{{ $help }}</small></span><input type="checkbox" role="switch" name="{{ $field }}" value="1" @checked(old($field, $club->$field))></label>
                @endforeach
            </div>
        </section>

        <div class="form-actions"><button class="button">{{ $club->exists ? 'Save settings' : 'Create club' }}</button><a class="button secondary" href="{{ $club->exists ? route('clubs.show', $club) : route('clubs.index') }}">Cancel</a></div>
    </form>
</div>
@endsection

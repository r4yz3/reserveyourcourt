@php($promptUser = auth()->user())
<dialog class="profile-prompt" data-profile-prompt aria-labelledby="profile-prompt-title">
    <form method="post" action="{{ route('play.profile.complete') }}" class="profile-prompt-form">@csrf
        <header>
            <div><h2 id="profile-prompt-title">Complete your profile</h2><p>A few details make open play matching fairer and help hosts know who is coming.</p></div>
            <button type="submit" formaction="{{ route('play.profile.dismiss') }}" formnovalidate class="icon-button" aria-label="Close and remind me later"><svg class="icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        </header>
        <div class="prompt-section">
            <label for="prompt_alias">Alias or nickname <span class="muted">(optional)</span></label>
            <input id="prompt_alias" name="alias" autofocus value="{{ old('alias', $promptUser->alias) }}" maxlength="40" placeholder="Shown to other players instead of your full name">
        </div>
        <fieldset class="prompt-section choice-row">
            <legend>Gender</legend>
            @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                <label class="choice"><input type="radio" name="gender" value="{{ $value }}" @checked(old('gender', $promptUser->gender) === $value)><span>{{ $label }}</span></label>
            @endforeach
        </fieldset>
        <div class="prompt-section">
            <label for="prompt_rating">Self rating</label>
            <x-rating-input id="prompt_rating" :value="old('self_rating', $promptUser->self_rating)" />
        </div>
        <div class="prompt-section">
            <label for="prompt_contact">Contact number</label>
            <input id="prompt_contact" name="contact_number" type="tel" autocomplete="tel" value="{{ old('contact_number', $promptUser->contact_number) }}" maxlength="30" placeholder="+63 9XX XXX XXXX">
            <small>Private. Only shared with hosts of open plays you join.</small>
        </div>
        <div class="prompt-section two">
            <div><label for="prompt_country">Country</label><select id="prompt_country" name="country">@foreach(\App\Http\Requests\UpdatePlayerProfileRequest::COUNTRIES as $country)<option @selected(old('country', $promptUser->country) === $country)>{{ $country }}</option>@endforeach</select></div>
            <div><label for="prompt_city">City</label><input id="prompt_city" name="home_city" list="ph-cities" value="{{ old('home_city', $promptUser->home_city) }}" maxlength="120" placeholder="Search a city or municipality"></div>
        </div>
        <footer>
            <button type="submit" formaction="{{ route('play.profile.dismiss') }}" formnovalidate class="button secondary">Not now</button>
            <button class="button">Save details</button>
        </footer>
    </form>
</dialog>
<x-city-options />

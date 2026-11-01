@extends('layouts.app')
@section('title', 'Add booking')
@section('content')
<a class="back-link" href="{{ route('dashboard',['business'=>$business->id]) }}"><x-icon name="left" size="15" />Court schedule</a>
<div class="heading"><div><h1>Add booking</h1><p class="muted">{{ $business->name }} · A place on court, reserved.</p></div><span class="form-security"><x-icon name="shield" size="15" />Staff reservation</span></div>
@if(!$fee)<div class="notice" role="status">The super admin must configure the booking fee before new reservations can be created.</div>@endif
@if($business->status==='suspended')<div class="notice error">This business is suspended and cannot accept new reservations.</div>@endif
<div class="booking-layout">
    <form class="booking-form" method="post" action="{{ route('bookings.store',$business) }}" data-booking-form data-fee-configured="{{ $fee ? 'true' : 'false' }}" data-fee="{{ $fee?->amount_centavos ?? 0 }}">
        @csrf
        <input type="hidden" name="request_key" value="{{ old('request_key',$requestKey) }}">
        <fieldset class="form-section"><legend>Player details</legend><p>Who's coming to play?</p><div class="form-grid">
            <div><label for="customer_name">Player name</label><input id="customer_name" name="customer_name" value="{{ old('customer_name') }}" required maxlength="120" autocomplete="name" placeholder="Full name" @error('customer_name') aria-invalid="true" aria-describedby="customer_name-error" @enderror>@error('customer_name')<span class="field-error" id="customer_name-error">{{ $message }}</span>@enderror</div>
            <div><label for="customer_contact">Phone or email</label><input id="customer_contact" name="customer_contact" value="{{ old('customer_contact') }}" required maxlength="120" autocomplete="off" placeholder="Contact details" @error('customer_contact') aria-invalid="true" aria-describedby="customer_contact-error" @enderror>@error('customer_contact')<span class="field-error" id="customer_contact-error">{{ $message }}</span>@enderror</div>
        </div></fieldset>
        <fieldset class="form-section"><legend>Court & time</legend><p>Choose where and when. All times are Philippine time.</p><div class="form-grid">
            <div><label for="court_id">Court</label><select id="court_id" name="court_id" required>@foreach($courts as $court)<option value="{{ $court->id }}" data-rate="{{ $court->hourly_rate_centavos }}" data-name="{{ $court->name }}" data-hours="{{ sprintf('%02d:00',$court->opens_at) }}–{{ sprintf('%02d:00',$court->closes_at) }}" @selected(old('court_id',$selectedCourt)==$court->id)>{{ $court->name }} · ₱{{ number_format($court->hourly_rate_centavos/100) }}/hr</option>@endforeach</select>@error('court_id')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div><label for="duration">Duration</label><select id="duration" name="duration">@foreach([30,60,90,120,150,180,240] as $minutes)<option value="{{ $minutes }}" @selected(old('duration',60)==$minutes)>{{ $minutes >= 60 ? floor($minutes/60).' hr'.($minutes%60 ? ' 30 min' : '') : '30 min' }}</option>@endforeach</select></div>
            <div class="span-all"><label for="starts_at">Start date & time</label><input id="starts_at" name="starts_at" type="datetime-local" step="1800" value="{{ old('starts_at',$startAt) }}" required aria-describedby="time-help" @error('starts_at') aria-invalid="true" @enderror><small id="time-help">Choose a :00 or :30 start within court hours.</small>@error('starts_at')<span class="field-error">{{ $message }}</span>@enderror</div>
        </div></fieldset>
        <fieldset class="form-section"><legend>Booking & payment</legend><p>A clear record for the player and your team.</p><div class="form-grid">
            <div><label for="payment_method">Payment method</label><select id="payment_method" name="payment_method"><option value="cash" @selected(old('payment_method','cash')==='cash')>Cash · paid in full</option><option value="instapay" @selected(old('payment_method')==='instapay')>InstaPay transfer</option></select></div>
            <div class="span-all cash-field"><label for="paid_amount">Full cash payment received</label><div class="money-input"><span>₱</span><input id="paid_amount" name="paid_amount" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('paid_amount',0) }}" aria-describedby="cash-help"></div><small id="cash-help">Enter the exact rental total received. Partial payment does not confirm a reservation.</small>@error('paid_amount')<span class="field-error">{{ $message }}</span>@enderror</div>
            <div class="span-all payment-guidance" data-payment-guidance><x-icon name="shield" size="16" /><span>Full payment is required before the reservation is confirmed.</span></div>
        </div></fieldset>
        <details class="optional-notes" @if(old('notes')) open @endif><summary>Add a note <span>Optional</span></summary><label class="sr-only" for="notes">Staff notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000" placeholder="Anything your team should know…">{{ old('notes') }}</textarea></details>
        <div class="form-actions"><button class="button" @disabled($courts->isEmpty() || !$fee || $business->status==='suspended')><span data-submit-label>Confirm paid reservation</span><x-icon name="arrow" size="16" /></button><a class="button quiet" href="{{ route('dashboard',['business'=>$business->id]) }}">Cancel</a></div>
    </form>
    <aside class="booking-summary" aria-label="Reservation estimate">
        <div class="summary-heading"><x-icon name="receipt" /><h2>Reservation summary</h2></div>
        <div class="summary-court"><span data-summary-court>{{ $courts->first()?->name ?? 'Choose a court' }}</span><small data-summary-time>Select your date and time</small></div>
        <dl class="summary-lines"><div><dt>Duration</dt><dd data-summary-duration>1 hour</dd></div><div><dt>Court rental</dt><dd data-summary-rental>—</dd></div><div><dt>Cash received</dt><dd data-summary-paid>₱0.00</dd></div></dl>
        <div class="summary-total"><span>Player balance</span><strong data-summary-balance>—</strong><small>Payable to the venue</small></div>
        <div class="summary-fee"><span>Venue platform fee</span><strong data-summary-fee>{{ $fee ? '₱'.number_format($fee->amount_centavos/100,2) : 'Not configured' }}</strong><small data-summary-fee-note>A separate platform fee for the venue.</small></div>
        <p class="summary-disclaimer"><x-icon name="shield" size="14" />Full payment is required. InstaPay reservations are confirmed only after verification.</p>
        <noscript><p class="small muted">The saved booking will show the calculated price.</p></noscript>
    </aside>
</div>
@endsection

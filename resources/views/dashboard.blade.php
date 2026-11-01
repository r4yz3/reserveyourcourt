@extends('layouts.app')
@section('title', 'Court schedule')
@section('content')
@php
    $bookedHours = $active->sum(fn($item) => $item->starts_at->diffInMinutes($item->ends_at)) / 60;
    $closureHours = $closures->sum(function($closure) use ($date, $courts) {
        $court = $courts->firstWhere('id',$closure->court_id);
        if (!$court) { return 0; }
        $from = $closure->starts_at->max($date->addHours($court->opens_at)->utc());
        $until = $closure->ends_at->min($date->addHours($court->closes_at)->utc());
        return $until->gt($from) ? $from->diffInMinutes($until)/60 : 0;
    });
    $openHours = max(0, $courts->sum(fn($item) => $item->closes_at - $item->opens_at) - $closureHours);
    $occupancy = $openHours > 0 ? min(100, round($bookedHours / $openHours * 100)) : 0;
    $opening = min($courts->min('opens_at') ?? 6, $active->min(fn($item) => $item->starts_at->setTimezone('Asia/Manila')->hour) ?? 24);
    $closing = max($courts->max('closes_at') ?? 22, $active->max(function($item) use ($date) {
        $end = $item->ends_at->setTimezone('Asia/Manila');
        return $end->isAfter($date->endOfDay()) ? 24 : $end->hour + ($end->minute > 0 ? 1 : 0);
    }) ?? 0);
    $slotCount = max(1, ($closing - $opening) * 2);
@endphp
<div class="heading">
    <div><h1>Court schedule</h1><p class="muted">A clear view of your day. A little more room to play.</p></div>
    @if($business)<a class="button" href="{{ route('bookings.create', ['business' => $business, 'start' => $date->setTime(9,0)->format('Y-m-d\TH:i')]) }}"><x-icon name="plus" />Add booking</a>@endif
</div>
<div class="venue-line">
    <form method="get" action="{{ route('dashboard') }}" class="venue-switcher">
        <x-icon name="pin" size="16" /><label class="sr-only" for="business">Business</label>
        <select id="business" name="business" data-auto-submit>@foreach($businesses as $item)<option value="{{ $item->id }}" @selected($business?->id === $item->id)>{{ $item->name }}</option>@endforeach</select>
        <input type="hidden" name="date" value="{{ $date->toDateString() }}"><noscript><button class="button secondary">Change venue</button></noscript>
    </form>
    @if($business?->is_demo)<span class="demo-label"><span></span>Demo workspace · Sample data only</span>@endif
</div>
<div class="metrics">
    <div><span>Reservations</span><strong>{{ $active->count() }}</strong><small>{{ number_format($bookedHours,1) }} court hours booked</small></div>
    <div><span>Court occupancy</span><strong>{{ $occupancy }}<em>%</em></strong><small>{{ $courts->count() }} courts · {{ number_format($openHours) }} available hours</small></div>
    <div><span>Collected payments</span><strong><em>₱</em>{{ number_format($active->sum('paid_centavos')/100,2) }}</strong><small>Recorded for this day</small></div>
    <div><span>Outstanding balance</span><strong><em>₱</em>{{ number_format(($active->sum('total_centavos')-$active->sum('paid_centavos'))/100,2) }}</strong><small>Across this day's reservations</small></div>
</div>
<div class="schedule-controls">
    <div class="date-navigation">
        <a class="icon-button" aria-label="Previous day" href="{{ route('dashboard',['business'=>$business?->id,'date'=>$date->subDay()->toDateString()]) }}"><x-icon name="left" /></a>
        <form method="get" action="{{ route('dashboard') }}" class="date-picker"><input type="hidden" name="business" value="{{ $business?->id }}"><label for="date"><x-icon name="calendar" />{{ $date->format('D, j M Y') }}</label><input id="date" type="date" name="date" value="{{ $date->toDateString() }}" required data-auto-submit><noscript><button>View day</button></noscript></form>
        <a class="icon-button" aria-label="Next day" href="{{ route('dashboard',['business'=>$business?->id,'date'=>$date->addDay()->toDateString()]) }}"><x-icon name="right" /></a>
        <a class="button secondary compact" href="{{ route('dashboard',['business'=>$business?->id,'date'=>now('Asia/Manila')->toDateString()]) }}">Today</a>
    </div>
    <div class="schedule-actions">@if($business)<a class="button secondary compact" href="{{ route('disruptions.index',['business'=>$business,'date'=>$date->toDateString()]) }}">Weather & court issues</a>@endif<div class="view-switch" aria-label="Schedule view"><button type="button" data-view="timeline" aria-pressed="true"><x-icon name="grid" size="15" />Timeline</button><button type="button" data-view="agenda" aria-pressed="false"><x-icon name="list" size="16" />Agenda</button></div></div>
</div>
<div class="desk-grid">
    <section class="schedule-main" data-schedule-view="timeline" aria-label="Court availability">
        <div class="section-heading"><h2>{{ $date->format('l') }}'s schedule <span class="subtle-count">{{ $active->count() }} reservations</span></h2><div class="schedule-legend">@if($active->isNotEmpty())<span><i class="legend-mark confirmed"></i>Reserved</span>@endif @if($active->whereIn('status',['held','awaiting_review'])->isNotEmpty())<span><i class="legend-mark pending"></i>Pending</span>@endif<span><i class="legend-mark open"></i>Open</span>@if($closures->isNotEmpty())<span><i class="legend-mark closed"></i>Closed</span>@endif</div></div>
        @if($courts->isEmpty())
            <div class="empty"><x-icon name="courts" size="28" /><h3>Your first court starts here.</h3><p>Add a court and its opening hours to start accepting reservations.</p>@if($business)<a class="button secondary" href="{{ route('venues.edit',$business) }}">Set up courts</a>@else<p>Ask your administrator to assign you to a business.</p>@endif</div>
        @else
            <div class="timeline-panel">
                <div class="timeline-scroll" tabindex="0" role="region" aria-label="Court timeline. Scroll horizontally to see all hours.">
                    <div class="timeline" style="--slots: {{ $slotCount }}">
                        <div class="timeline-header"><div class="court-column">Court / time</div>@for($hour=$opening; $hour<$closing; $hour++)<div class="hour-label">{{ sprintf('%02d:00',$hour) }}</div>@endfor</div>
                        @foreach($courts as $court)
                            <div class="timeline-row">
                                <div class="court-column"><strong>{{ $court->name }}</strong><span>{{ $court->type }}</span><small>{{ $court->is_active ? '₱'.number_format($court->hourly_rate_centavos/100).' / hr' : 'Inactive' }}</small></div>
                                @for($slot=0; $slot<$slotCount; $slot++)
                                    @php
                                        $time = $date->addHours($opening)->addMinutes($slot*30);
                                        $reservation = $active->first(fn($item) => $item->court_id === $court->id && $item->starts_at->lte($time) && $item->ends_at->gt($time));
                                        $isStart = $reservation && $reservation->starts_at->equalTo($time);
                                        $closure = $closures->first(fn($item) => $item->court_id === $court->id && $item->starts_at->lte($time) && $item->ends_at->gt($time));
                                        $isClosed = !$court->is_active || $closure || $time->hour < $court->opens_at || $time->hour >= $court->closes_at;
                                        $isPast = $time->isPast();
                                    @endphp
                                    @if($isStart)
                                        @php($span = max(1, (int) ($reservation->starts_at->diffInMinutes($reservation->ends_at)/30)))
                                        <a class="timeline-booking {{ $reservation->status }}" style="grid-column: {{ $slot+2 }} / span {{ $span }}" href="{{ route('bookings.show',$reservation) }}" aria-label="{{ $reservation->customer_name }}, {{ $court->name }}, {{ $reservation->starts_at->setTimezone('Asia/Manila')->format('g:i A') }} to {{ $reservation->ends_at->setTimezone('Asia/Manila')->format('g:i A') }}, {{ str_replace('_',' ',$reservation->status) }}" title="{{ $reservation->customer_name }} · {{ $reservation->starts_at->setTimezone('Asia/Manila')->format('g:i A') }}–{{ $reservation->ends_at->setTimezone('Asia/Manila')->format('g:i A') }} · {{ str_replace('_',' ',$reservation->status) }}"><strong>{{ $reservation->customer_name }}</strong><span>{{ $reservation->starts_at->setTimezone('Asia/Manila')->format('g:i') }}–{{ $reservation->ends_at->setTimezone('Asia/Manila')->format('g:i A') }}</span><small>{{ $reservation->coach ? 'Coach '.$reservation->coach->user->name : ($reservation->source === 'walk_in' ? 'Historical booking' : 'Online') }}</small></a>
                                    @elseif(!$reservation)
                                        @if($isClosed || $isPast)<div class="time-slot unavailable {{ $closure ? 'maintenance-slot' : '' }}" title="{{ $closure ? 'Unavailable: '.$closure->reason : 'Unavailable' }}" style="grid-column: {{ $slot+2 }}" aria-label="{{ $court->name }} {{ $time->format('g:i A') }} · {{ $isClosed ? 'Closed' : 'Past time' }}"></div>
                                        @else<a class="time-slot" style="grid-column: {{ $slot+2 }}" href="{{ route('bookings.create',['business'=>$business,'court'=>$court->id,'start'=>$time->format('Y-m-d\TH:i')]) }}" aria-label="Book {{ $court->name }} at {{ $time->format('g:i A') }}" title="Book {{ $time->format('g:i A') }}"><span aria-hidden="true">+</span></a>@endif
                                    @endif
                                @endfor
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="timeline-footer"><span><x-icon name="plus" size="14" />Select an open time to book</span><span>30-minute intervals · PHT</span></div>
            </div>
            <div class="court-agendas">
                @foreach($courts as $court)
                    <article class="court-agenda"><header><div><h3>{{ $court->name }}</h3><span class="muted small">{{ $court->type }} · {{ sprintf('%02d:00',$court->opens_at) }}–{{ sprintf('%02d:00',$court->closes_at) }}</span></div><span class="court-rate">₱{{ number_format($court->hourly_rate_centavos/100) }}<small> / hr</small></span></header>
                        @forelse($active->where('court_id',$court->id) as $reservation)
                            <a class="reservation {{ $reservation->status }}" href="{{ route('bookings.show',$reservation) }}"><div class="reservation-time">{{ $reservation->starts_at->setTimezone('Asia/Manila')->format('g:i A') }}<span>{{ $reservation->ends_at->setTimezone('Asia/Manila')->format('g:i A') }}</span></div><span class="player-avatar">{{ mb_substr($reservation->customer_name,0,1) }}</span><div class="reservation-player"><strong>{{ $reservation->customer_name }}</strong><span>{{ $reservation->source === 'walk_in' ? 'Historical booking' : 'Online reservation' }}</span></div><div class="reservation-state"><span class="status">{{ str_replace('_',' ',$reservation->status) }}</span><small>{{ $reservation->paid_centavos >= $reservation->total_centavos ? 'Paid in full' : 'Balance ₱'.number_format(($reservation->total_centavos-$reservation->paid_centavos)/100,2) }}</small></div><x-icon name="right" size="15" /></a>
                        @empty<div class="free-slot"><span class="availability-dot"></span>Your court is clear for the day.</div>@endforelse
                        @foreach($closures->where('court_id',$court->id) as $closure)<div class="agenda-closure"><x-icon name="clock" size="15" /><span><strong>{{ $closure->reason }}</strong><small>{{ $closure->starts_at->setTimezone('Asia/Manila')->format('j M, g:i A') }}–{{ $closure->ends_at->setTimezone('Asia/Manila')->format('j M, g:i A') }} · Closed</small></span></div>@endforeach
                        @if($court->is_active)<a class="add-slot" href="{{ route('bookings.create',['business'=>$business,'court'=>$court->id,'start'=>$date->setTime($court->opens_at,0)->format('Y-m-d\TH:i')]) }}"><x-icon name="plus" size="15" />Add reservation</a>@endif
                    </article>
                @endforeach
            </div>
        @endif
        @if($bookings->count() > $active->count())<details class="archived"><summary>{{ $bookings->count()-$active->count() }} cancelled or expired reservations</summary>@foreach($bookings->whereNotIn('id',$active->pluck('id')) as $reservation)<p><a href="{{ route('bookings.show',$reservation) }}">{{ $reservation->customer_name }} · {{ $reservation->status }}</a></p>@endforeach</details>@endif
    </section>
    <aside class="review-rail {{ $pending->isNotEmpty() ? 'has-pending' : '' }}">
        <div class="section-heading"><h2>Payment review</h2><span class="count">{{ $pending->count() }}</span></div>
        @forelse($pending as $reservation)<a class="review-item" href="{{ route('bookings.show',$reservation) }}"><span class="review-top"><strong>{{ $reservation->customer_name }}</strong><x-icon name="right" size="14" /></span><span>{{ $reservation->court->name }} · {{ $reservation->starts_at->setTimezone('Asia/Manila')->format('M j, g:i A') }}</span><b>₱{{ number_format($reservation->total_centavos/100,2) }}</b><small>Review by {{ $reservation->hold_expires_at->setTimezone('Asia/Manila')->format('g:i A') }}</small></a>
        @empty<div class="review-clear"><span class="review-check"><x-icon name="check" size="24" /></span><strong>You're all caught up.</strong><p>No transfer receipts waiting.<br>New submissions will appear here.</p></div>@endforelse
        <p class="rail-caption"><x-icon name="shield" size="14" />Verify actual funds in your bank or wallet before approving.</p>
        <div class="policy-note"><div class="policy-heading"><x-icon name="shield" /><h3>Paid in full. Ready to play.</h3></div><p>Reservations are confirmed only after full payment. Verify InstaPay funds before approving a receipt.</p></div>
        @if(auth()->user()->is_super_admin)<a class="fee-link" href="{{ route('fees.edit') }}"><x-icon name="settings" /><span>Online booking fee<strong>{{ $fee ? '₱'.number_format($fee->amount_centavos/100,2).' per booking' : 'Not configured' }}</strong></span><x-icon name="right" size="14" /></a>@endif
    </aside>
</div>
@endsection

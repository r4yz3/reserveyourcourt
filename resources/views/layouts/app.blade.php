<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="theme-color" content="#0e1c34">
    <title>@yield('title', 'Court schedule') · Court desk</title>
    <link rel="preload" href="{{ asset('fonts/instrument-sans.ttf') }}" as="font" type="font/ttf" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/desk.css') }}?v=5">
    <script src="{{ asset('js/desk.js') }}?v=2" defer></script>
</head>
<body>
@php
    $coachMode = request()->routeIs('coach.*');
    $hasVenueAccess = auth()->user()->is_super_admin || auth()->user()->businesses()->exists();
    $workspaceBusiness = $business ?? (isset($booking) ? $booking->business : null);
@endphp
<a class="skip" href="#main">Skip to content</a>
<div class="app-frame">
    <aside class="sidebar" id="workspace-nav">
        <a class="brand" href="{{ route($coachMode ? 'coach.portal' : 'dashboard') }}"><span class="court-mark" aria-hidden="true"><i></i></span><span>Court desk<span class="brand-sub">{{ $coachMode ? 'Coach portal' : 'Venue management' }}</span></span></a>
        <button type="button" class="icon-button mobile-menu" aria-label="Toggle navigation" aria-controls="workspace-menu" aria-expanded="false" data-menu-toggle><x-icon name="menu" /></button>
        <div class="sidebar-content" id="workspace-menu">
            <div class="workspace-label"><span class="workspace-symbol"><x-icon name="courts" /></span><span>{{ $coachMode ? 'Coach workspace' : 'Venue workspace' }}<small>{{ auth()->user()->is_super_admin ? 'Platform administrator' : ($coachMode ? 'Lessons & availability' : 'Business operations') }}</small></span></div>
            @php
                $coachTab = request()->routeIs('coach.students.*') ? 'students' : request('tab', 'lessons');
                $navItems = fn (array $items): array => array_filter($items);
                $navGroups = [
                'Operations' => $navItems([
                    $hasVenueAccess ? ['Court schedule', 'calendar', route('dashboard', $workspaceBusiness ? ['business' => $workspaceBusiness->id] : []), request()->routeIs('dashboard', 'bookings.*', 'disruptions.*')] : null,
                    $workspaceBusiness && auth()->user()->canManageBusiness($workspaceBusiness->id) ? ['Venue setup', 'settings', route('venues.edit', $workspaceBusiness), request()->routeIs('venues.*', 'courts.*', 'closures.*')] : null,
                    $workspaceBusiness && auth()->user()->canManageBusiness($workspaceBusiness->id) ? ['Venue coaches', 'users', route('affiliations.index', $workspaceBusiness), request()->routeIs('affiliations.*')] : null,
                ]),
                'Coaching' => $navItems($coachMode ? [
                    ['Lessons', 'calendar', route('coach.portal'), request()->routeIs('coach.portal', 'coach.lessons.*') && $coachTab === 'lessons'],
                    ['Students', 'users', route('coach.students.index'), request()->routeIs('coach.students.*')],
                    ['Profile & hours', 'settings', route('coach.portal', ['tab' => 'profile']), request()->routeIs('coach.portal') && in_array($coachTab, ['profile', 'availability', 'venues', 'time-off'], true)],
                    ['Public preview', 'eye', route('coach.preview'), request()->routeIs('coach.preview')],
                ] : [
                    auth()->user()->coach()->exists() ? ['Coach workspace', 'users', route('coach.portal'), false] : null,
                ]),
                'Platform' => $navItems(auth()->user()->is_super_admin ? [
                    ['Businesses', 'building', route('businesses.index'), request()->routeIs('businesses.*')],
                    ['Coach approvals', 'shield', route('coaches.approvals'), request()->routeIs('coaches.approvals*')],
                    ['Booking fee', 'coin', route('fees.edit'), request()->routeIs('fees.*')],
                ] : []),
                ];
                if ($coachMode) {
                    $navGroups = ['Coaching' => $navGroups['Coaching'], 'Operations' => $navGroups['Operations'], 'Platform' => $navGroups['Platform']];
                }
            @endphp
            <nav aria-label="Workspace">
                @foreach($navGroups as $groupLabel => $items)
                    @if(count($items))
                        <p class="nav-caption">{{ $groupLabel }}</p>
                        @foreach($items as [$label, $icon, $href, $isCurrent])
                            <a class="nav-link {{ $isCurrent ? 'current' : '' }}" @if($isCurrent) aria-current="page" @endif href="{{ $href }}"><x-icon :name="$icon" />{{ $label }}</a>
                        @endforeach
                    @endif
                @endforeach
            </nav>
            <div class="sidebar-note"><x-icon name="shield" /><strong>{{ $coachMode ? 'Room to help players grow.' : 'A space for your business.' }}</strong><p>{{ $coachMode ? 'Your lessons, availability and private coaching notes, together.' : 'Your courts, bookings and payment records stay within your workspace.' }}</p></div>
            <div class="sidebar-foot"><span class="avatar">{{ mb_substr(auth()->user()->name,0,1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->is_super_admin ? 'Super admin' : ($coachMode ? 'Coach' : 'Venue team') }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sign out"><x-icon name="logout" /></button></form></div>
        </div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div><span class="breadcrumb-root">Workspace</span><span class="breadcrumb-separator">/</span><span>@yield('title', 'Court schedule')</span></div><div class="topbar-right"><span class="timezone"><x-icon name="clock" size="14" /> Philippine time</span><span class="currency-label">PHP</span></div></header>
        <main id="main" class="sheet">
            @if(session('success'))<div class="notice success" role="status"><x-icon name="check" /><span>{{ session('success') }}</span></div>@endif
            @if($errors->any())<div class="notice error" role="alert" tabindex="-1" data-error-summary><strong>A few details need your attention.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            @yield('content')
            <footer class="page-foot"><span>Court desk</span><span>More time for the game.</span></footer>
        </main>
    </div>
</div>
</body>
</html>

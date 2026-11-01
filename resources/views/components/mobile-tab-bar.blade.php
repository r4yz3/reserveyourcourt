@php
    $accountRoute = auth()->user()?->homeRoute() ?? 'login';
    $accountLabel = match ($accountRoute) {
        'login' => 'Sign in',
        'play.dashboard' => 'My games',
        default => 'Workspace',
    };
    $tabs = [
        ['Courts', 'courts', 'public.venues.index', ['home', 'public.venues.*', 'public.reservations.*']],
        ['Coaches', 'clipboard', 'public.coaches.index', ['public.coaches.*']],
        ['Open play', 'calendar', 'open-play.index', ['open-play.*']],
        ['Clubs', 'users', 'clubs.index', ['clubs.*']],
        [$accountLabel, 'user', $accountRoute, ['play.*', 'players.*']],
    ];
@endphp
<nav class="mobile-tab-bar" aria-label="Main navigation">@foreach($tabs as [$label, $icon, $route, $activeRoutes])<a href="{{ route($route) }}" @if(request()->routeIs(...$activeRoutes)) aria-current="page" @endif><span class="mobile-tab-icon"><x-icon :name="$icon" size="20" /></span>{{ $label }}</a>@endforeach</nav>

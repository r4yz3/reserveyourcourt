@props(['name', 'size' => 18])
@php
    $paths = [
        'calendar' => 'M8 2v4m8-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2zM7 14h3m4 0h3m-10 3h3',
        'courts' => 'M3 3h18v18H3zM12 3v18M3 12h18M3 8h18M3 16h18',
        'settings' => 'M4 7h9m4 0h3M4 17h3m4 0h9M13 4v6M7 14v6',
        'arrow' => 'M5 12h14m-5-5 5 5-5 5',
        'left' => 'm14 6-6 6 6 6',
        'right' => 'm10 6 6 6-6 6',
        'plus' => 'M12 5v14M5 12h14',
        'clock' => 'M12 8v5l3 2M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'check' => 'm6 12 4 4 8-8',
        'receipt' => 'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 7h6m-6 4h6m-6 4h3',
        'logout' => 'M9 4H4v16h5m5-12 4 4-4 4m-5-4h12',
        'grid' => 'M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h7v7h-7z',
        'list' => 'M9 6h12M9 12h12M9 18h12M3 6h1m-1 6h1m-1 6h1',
        'shield' => 'M12 3 4 6v6c0 5 8 9 8 9s8-4 8-9V6zM8 12l3 3 5-6',
        'chevron' => 'm7 10 5 5 5-5',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'users' => 'M16 20v-1a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v1M9.5 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7zM21 20v-1a4 4 0 0 0-3-3.87M16 4.13a3.5 3.5 0 0 1 0 6.75',
        'building' => 'M4 21V5l8-3 8 3v16M4 21h16M9 21v-4h6v4M8 8h1m3 0h1m3 0h1M8 12h1m3 0h1m3 0h1',
        'coin' => 'M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0zM15 9.5c-.5-1-1.6-1.5-3-1.5-1.7 0-3 .8-3 2s1.3 1.7 3 2 3 .8 3 2-1.3 2-3 2c-1.4 0-2.5-.5-3-1.5M12 6.5v11',
        'eye' => 'M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12zM15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
        'pin' =>'M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0zM15 10a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
    ];
@endphp
<svg {{ $attributes->merge(['class' => 'icon']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $paths[$name] ?? $paths['calendar'] }}" /></svg>

@props(['club', 'size' => 56])
@if($club->logo_path)
<img {{ $attributes->merge(['class' => 'club-logo']) }} src="{{ route('clubs.media', ['club' => $club, 'kind' => 'logo']) }}?v={{ substr(md5($club->logo_path), 0, 8) }}" width="{{ $size }}" height="{{ $size }}" alt="{{ $club->name }} logo" style="--logo-size: {{ $size }}px" loading="lazy">
@else
<span {{ $attributes->merge(['class' => 'club-logo initials']) }} style="--logo-size: {{ $size }}px" aria-hidden="true">{{ $club->initials() }}</span>
@endif

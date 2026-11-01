@props(['user', 'size' => 40])
@if($user->photo_path && ($user->show_profile || auth()->user()?->is($user)))
<img {{ $attributes->merge(['class' => 'player-avatar']) }} src="{{ route('players.photo', $user) }}?v={{ substr(md5($user->photo_path), 0, 8) }}" width="{{ $size }}" height="{{ $size }}" alt="" style="--avatar-size: {{ $size }}px">
@else
<span {{ $attributes->merge(['class' => 'player-avatar initials']) }} style="--avatar-size: {{ $size }}px" aria-hidden="true">{{ $user->initials() }}</span>
@endif

@props(['booking'])
@php
    $holdOpen = $booking->hold_expires_at?->isFuture();
    [$tone, $label] = match (true) {
        $booking->status === 'confirmed' => ['confirmed', 'Confirmed'],
        $booking->status === 'completed' => ['completed', 'Played'],
        $booking->status === 'awaiting_review' && $holdOpen => ['awaiting_review', 'Receipt under review'],
        $booking->status === 'held' && $holdOpen => ['held', 'Awaiting payment'],
        in_array($booking->status, ['held', 'awaiting_review'], true) => ['expired', 'Payment window closed'],
        $booking->status === 'no_show' => ['cancelled', 'Missed'],
        default => ['cancelled', ucfirst(str_replace('_', ' ', $booking->status))],
    };
@endphp
<span class="status {{ $tone }}">{{ $label }}</span>

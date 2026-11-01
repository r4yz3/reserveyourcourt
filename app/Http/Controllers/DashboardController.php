<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Business;
use App\Models\CourtClosure;
use App\Models\FeeSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'business' => ['nullable', 'integer']]);
        $businesses = $request->user()->is_super_admin ? Business::query()->orderBy('name')->get() : $request->user()->businesses()->orderBy('name')->get();
        $business = isset($data['business']) ? $businesses->firstWhere('id', (int) $data['business']) : $businesses->first();
        abort_if(isset($data['business']) && ! $business, 404);
        $date = CarbonImmutable::parse($data['date'] ?? now('Asia/Manila')->toDateString(), 'Asia/Manila')->startOfDay();
        $courts = $business ? $business->courts()->where(fn ($query) => $query->where('is_active', true)->orWhereHas('bookings', fn ($bookings) => $bookings->where('starts_at', '>=', $date->utc())->where('starts_at', '<', $date->addDay()->utc())))->orderBy('name')->get() : collect();
        $bookings = $business ? $business->bookings()->with(['court', 'coach.user'])->where('starts_at', '>=', $date->utc())->where('starts_at', '<', $date->addDay()->utc())->orderBy('starts_at')->get() : collect();
        $pending = $business ? $business->bookings()->with(['court', 'coach.user', 'proofs'])->where('status', 'awaiting_review')->where('hold_expires_at', '>', now())->orderBy('starts_at')->limit(20)->get() : collect();
        $active = $bookings->filter(fn (Booking $booking): bool => in_array($booking->status, ['confirmed', 'completed', 'no_show'], true) || (in_array($booking->status, ['held', 'awaiting_review'], true) && $booking->hold_expires_at?->isFuture()));

        $closures = $business ? CourtClosure::query()->where('business_id', $business->id)->whereNull('cancelled_at')->where('starts_at', '<', $date->addDay()->utc())->where('ends_at', '>', $date->utc())->get() : collect();

        return view('dashboard', compact('closures', 'businesses', 'business', 'date', 'courts', 'bookings', 'pending', 'active') + ['fee' => FeeSetting::query()->latest('id')->first()]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Business;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CourtDisruptionController extends Controller
{
    public function index(Request $request, Business $business): View
    {
        abort_unless($request->user()->canAccessBusiness($business->id), 404);
        $data = $request->validate(['court' => ['nullable', 'integer'], 'date' => ['nullable', 'date_format:Y-m-d'], 'from' => ['nullable', 'date_format:Y-m-d\TH:i'], 'until' => ['nullable', 'date_format:Y-m-d\TH:i']]);
        $courts = $business->courts()->orderBy('name')->get();
        $court = isset($data['court']) ? $courts->firstWhere('id', (int) $data['court']) : ($courts->firstWhere('type', 'Outdoor') ?? $courts->first());
        abort_if(isset($data['court']) && ! $court, 404);
        $date = CarbonImmutable::parse($data['date'] ?? now('Asia/Manila')->toDateString(), 'Asia/Manila')->startOfDay();
        $from = isset($data['from']) ? CarbonImmutable::parse($data['from'], 'Asia/Manila') : $date->addHours($court?->opens_at ?? 6);
        $until = isset($data['until']) ? CarbonImmutable::parse($data['until'], 'Asia/Manila') : $from->startOfDay()->addHours($court?->closes_at ?? 22);
        if ($until->lte($from) || $until->gt($from->addDays(7))) {
            throw ValidationException::withMessages(['until' => 'Choose an end after the start, within seven days.']);
        }
        $query = $business->bookings()->with(['court', 'coach.user'])->when($court, fn ($q) => $q->where('court_id', $court->id))->where('starts_at', '<', $until->utc())->where('ends_at', '>', $from->utc())->where(fn ($q) => $q->where('status', 'confirmed')->orWhere(fn ($hold) => $hold->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '>', now())));
        $readyCount = (clone $query)->where('status', 'confirmed')->where('total_centavos', '>', 0)->whereColumn('paid_centavos', 'total_centavos')->count();
        $bookings = $query->orderBy('starts_at')->orderBy('id')->paginate(25)->withQueryString();

        return view('courts.disruptions', compact('business', 'courts', 'court', 'from', 'until', 'bookings', 'readyCount'));
    }
}

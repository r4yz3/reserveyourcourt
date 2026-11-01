<?php

namespace App\Http\Controllers;

use App\Actions\CoachSchedule;
use App\Actions\CreateBooking;
use App\Http\Requests\StoreCoachLessonRequest;
use App\Models\Coach;
use App\Models\FeeSetting;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PublicCoachController extends Controller
{
    private function visible(): Builder
    {
        return Coach::query()->where('status', 'approved')->where('is_accepting', true)->where('hourly_rate_centavos', '>', 0)->whereNotNull('bio')->whereHas('availability')->whereHas('businesses', fn ($q) => $q->where('business_coach.status', 'approved')->where('businesses.status', 'approved')->where('is_demo', false)->whereNotNull('payment_instructions')->where('payment_instructions', '!=', '')->whereHas('courts', fn ($court) => $court->where('is_active', true)));
    }

    public function index(Request $request): View
    {
        $data = $request->validate(['business' => ['nullable', 'integer']]);
        $coaches = $this->visible()->with(['user', 'businesses' => fn ($q) => $q->where('business_coach.status', 'approved')->where('businesses.status', 'approved')->where('is_demo', false)])->when($data['business'] ?? null, fn ($q, $id) => $q->whereHas('businesses', fn ($venue) => $venue->where('businesses.id', $id)->where('business_coach.status', 'approved')))->orderBy('id')->paginate(12)->withQueryString();

        return view('coaches.directory', compact('coaches'));
    }

    public function show(Request $request, Coach $coach, CoachSchedule $schedule): View
    {
        $coach = $this->visible()->with('user')->findOrFail($coach->id);

        return $this->renderBooking($request, $coach, $schedule, false);
    }

    public function preview(Request $request, CoachSchedule $schedule): View|RedirectResponse
    {
        $coach = $request->user()->coach()->with('user')->firstOrFail();
        if (! $coach->businesses()->wherePivot('status', 'approved')->whereIn('businesses.status', ['draft', 'approved'])->whereHas('courts', fn ($q) => $q->where('is_active', true))->exists()) {
            return redirect()->route('coach.portal', ['tab' => 'venues'])->with('success', 'An approved venue affiliation with an active court is needed for the lesson-page preview.');
        }

        return $this->renderBooking($request, $coach, $schedule, true);
    }

    private function renderBooking(Request $request, Coach $coach, CoachSchedule $schedule, bool $preview): View
    {
        $data = $request->validate(['business' => ['nullable', 'integer'], 'date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.now('Asia/Manila')->toDateString(), 'before_or_equal:'.now('Asia/Manila')->addDays(90)->toDateString()], 'duration' => ['nullable', 'integer', 'in:30,60,90,120']]);
        $venues = $coach->businesses()->wherePivot('status', 'approved')->whereIn('businesses.status', $preview ? ['draft', 'approved'] : ['approved'])->when(! $preview, fn ($query) => $query->where('is_demo', false)->whereNotNull('payment_instructions')->where('payment_instructions', '!=', ''))->whereHas('courts', fn ($q) => $q->where('is_active', true))->orderBy('name')->get();
        $business = isset($data['business']) ? $venues->firstWhere('id', (int) $data['business']) : $venues->first();
        abort_unless($business, 404);
        $date = CarbonImmutable::parse($data['date'] ?? now('Asia/Manila')->toDateString(), 'Asia/Manila')->startOfDay();
        $duration = (int) ($data['duration'] ?? 60);
        $courts = $business->courts()->where('is_active', true)->orderBy('name')->get();
        $slots = $schedule->slots($coach, $courts, $date, $duration);

        return view('coaches.book', compact('coach', 'venues', 'business', 'date', 'duration', 'courts', 'slots') + ['requestKey' => (string) Str::uuid(), 'preview' => $preview, 'bookingEnabled' => ! $preview && FeeSetting::query()->exists()]);
    }

    public function store(StoreCoachLessonRequest $request, Coach $coach, CreateBooking $create): RedirectResponse
    {
        $coach = $this->visible()->findOrFail($coach->id);
        $business = $coach->businesses()->wherePivot('status', 'approved')->where('businesses.status', 'approved')->where('is_demo', false)->findOrFail($request->integer('business_id'));
        $court = $business->courts()->where('is_active', true)->findOrFail($request->integer('court_id'));
        if (! $request->session()->has('player_key')) {
            $request->session()->put('player_key', Str::random(64));
        }
        $booking = $create->handleGuestLesson($court, $coach, $request->validated() + ['player_id' => $request->user()?->id], $request->session()->get('player_key'));

        return redirect()->route('public.reservations.show', $booking->reference);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\CoachSchedule;
use App\Actions\LockBooking;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Business;
use App\Models\Coach;
use App\Models\CoachBlock;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CoachPortalController extends Controller
{
    public function index(Request $request): View
    {
        $coach = $request->user()->coach()->with(['availability', 'businesses'])->first();
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'tab' => ['nullable', 'in:lessons,profile,availability,venues,time-off']]);
        $date = CarbonImmutable::parse($data['date'] ?? now('Asia/Manila')->toDateString(), 'Asia/Manila')->startOfDay();
        if (! $coach) {
            return view('coaches.start');
        }
        $dayView = isset($data['date']);
        $lessons = $coach->bookings()->with(['court', 'business'])
            ->when($dayView, fn ($query) => $query->where('starts_at', '>=', $date->utc())->where('starts_at', '<', $date->addDay()->utc()), fn ($query) => $query->where('ends_at', '>', now())->where(fn ($active) => $active->where('status', 'confirmed')->orWhere(fn ($hold) => $hold->whereIn('status', ['held', 'awaiting_review'])->where('hold_expires_at', '>', now()))))
            ->orderBy('starts_at')->orderBy('id')->paginate(20)->withQueryString();
        $recordedFees = $coach->bookings()->whereIn('status', ['confirmed', 'completed', 'no_show'])->whereColumn('paid_centavos', 'total_centavos')->sum('coaching_centavos');
        $venues = Business::query()->where('status', 'approved')->where('is_demo', false)->whereHas('courts', fn ($q) => $q->where('is_active', true))->whereDoesntHave('coaches', fn ($q) => $q->where('coaches.id', $coach->id))->orderBy('name')->get(['id', 'name', 'city']);

        return view('coaches.portal', ['coach' => $coach, 'dayView' => $dayView, 'tab' => $data['tab'] ?? 'lessons', 'lessons' => $lessons, 'date' => $date, 'recordedFees' => $recordedFees, 'venues' => $venues, 'blocks' => $coach->blocks()->where('ends_at', '>', now())->orderBy('starts_at')->paginate(10, ['*'], 'blocks_page')->withQueryString()]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $coach = $request->user()->coach()->firstOrFail();
        $data = $request->validate(['bio' => ['required', 'string', 'max:2000'], 'specialties' => ['required', 'string', 'max:255'], 'hourly_rate' => ['required', 'regex:/^\d{1,6}(?:\.\d{1,2})?$/D', 'numeric', 'min:1'], 'is_accepting' => ['required', 'boolean']]);
        DB::transaction(function () use ($coach, $data): void {
            Coach::query()->lockForUpdate()->findOrFail($coach->id)->update(['bio' => $data['bio'], 'specialties' => $data['specialties'], 'hourly_rate_centavos' => Money::centavos($data['hourly_rate']), 'is_accepting' => (bool) $data['is_accepting']]);
        });

        return back()->with('success', 'Coach profile saved. Rates apply to new lessons only.');
    }

    public function availability(Request $request, CoachSchedule $schedule): RedirectResponse
    {
        $coach = $request->user()->coach()->firstOrFail();
        $data = $request->validate(['days' => ['required', 'array:0,1,2,3,4,5,6', 'size:7'], 'days.*' => ['required', 'array:enabled,start,end'], 'days.*.enabled' => ['required', 'boolean'], 'days.*.start' => ['required', 'integer', 'min:0', 'max:1410', 'multiple_of:30'], 'days.*.end' => ['required', 'integer', 'min:30', 'max:1440', 'multiple_of:30']]);
        DB::transaction(function () use ($coach, $data, $schedule): void {
            $coach = Coach::query()->lockForUpdate()->findOrFail($coach->id);
            foreach ($data['days'] as $day) {
                if ($day['enabled'] && $day['end'] <= $day['start']) {
                    throw ValidationException::withMessages(['days' => 'Each enabled day must end after its start.']);
                }
            }
            $lessons = $schedule->activeLessons($coach)->where('ends_at', '>', now())->get(['starts_at', 'ends_at']);
            foreach ($lessons as $lesson) {
                $start = $lesson->starts_at->setTimezone('Asia/Manila');
                $day = $data['days'][$start->dayOfWeek] ?? null;
                $end = (int) $start->startOfDay()->diffInMinutes($lesson->ends_at);
                if (! $day || ! $day['enabled'] || $start->hour * 60 + $start->minute < $day['start'] || $end > $day['end']) {
                    throw ValidationException::withMessages(['days' => 'These hours exclude an upcoming lesson or active payment hold. Resolve that reservation first.']);
                }
            }
            $coach->availability()->delete();
            foreach ($data['days'] as $weekday => $day) {
                if ($day['enabled']) {
                    $coach->availability()->create(['weekday' => $weekday, 'start_minute' => $day['start'], 'end_minute' => $day['end']]);
                }
            }
        });

        return back()->with('success', 'Weekly availability updated. Existing reservations remain protected.');
    }

    public function block(Request $request, CoachSchedule $schedule): RedirectResponse
    {
        $coach = $request->user()->coach()->firstOrFail();
        $data = $request->validate(['starts_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:now'], 'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'], 'reason' => ['required', 'string', 'max:255']]);
        $start = CarbonImmutable::parse($data['starts_at'], 'Asia/Manila')->utc();
        $end = CarbonImmutable::parse($data['ends_at'], 'Asia/Manila')->utc();
        if ($start->isPast()) {
            throw ValidationException::withMessages(['starts_at' => 'Time off must start in the future, in Philippine time.']);
        }
        if ($end->gt($start->addDays(90))) {
            throw ValidationException::withMessages(['ends_at' => 'Time off may cover up to 90 days.']);
        }
        DB::transaction(function () use ($coach, $data, $schedule, $start, $end): void {
            $coach = Coach::query()->lockForUpdate()->findOrFail($coach->id);
            if ($schedule->activeLessons($coach)->where('starts_at', '<', $end)->where('ends_at', '>', $start)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'Time off overlaps a lesson or active payment hold. Arrange a replacement with the venue first.']);
            }
            $coach->blocks()->create(['starts_at' => $start, 'ends_at' => $end, 'reason' => $data['reason']]);
        });

        return back()->with('success', 'Time off added across all affiliated venues.');
    }

    public function destroyBlock(Request $request, CoachBlock $block): RedirectResponse
    {
        $coach = $request->user()->coach()->firstOrFail();
        abort_unless($block->coach_id === $coach->id, 404);
        DB::transaction(function () use ($coach, $block): void {
            Coach::query()->whereKey($coach->id)->lockForUpdate()->firstOrFail();
            $block->delete();
        });

        return back()->with('success', 'Time off removed.');
    }

    public function lesson(Request $request, Booking $booking): View
    {
        $coach = $request->user()->coach()->firstOrFail();
        abort_unless($booking->coach_id === $coach->id, 404);
        $booking->load(['court', 'business', 'reschedules.fromCourt', 'reschedules.toCourt']);

        return view('coaches.lesson', compact('booking', 'coach'));
    }

    public function updateLesson(Request $request, Booking $booking, LockBooking $lock): RedirectResponse
    {
        $coach = $request->user()->coach()->firstOrFail();
        abort_unless($booking->coach_id === $coach->id, 404);
        $data = $request->validate(['coach_notes' => ['nullable', 'string', 'max:5000'], 'lesson_attendance' => ['nullable', 'in:attended,no_show']]);
        DB::transaction(function () use ($request, $booking, $data, $lock): void {
            $booking = $lock->handle($booking);
            abort_unless(in_array($booking->status, ['confirmed', 'completed', 'no_show'], true) && $booking->paid_centavos === $booking->total_centavos, 409);
            if (! empty($data['lesson_attendance']) && ! $booking->ends_at->isPast()) {
                throw ValidationException::withMessages(['lesson_attendance' => 'Record attendance after the lesson has ended.']);
            }
            $booking->update(['coach_notes' => $data['coach_notes'] ?? null, 'lesson_attendance' => array_key_exists('lesson_attendance', $data) ? $data['lesson_attendance'] : $booking->lesson_attendance]);
            AuditEvent::query()->create(['user_id' => $request->user()->id, 'business_id' => $booking->business_id, 'action' => 'coach.lesson_recorded', 'details' => ['booking_id' => $booking->id, 'attendance' => $booking->lesson_attendance]]);
        });

        return back()->with('success', 'Private lesson notes and attendance saved.');
    }
}

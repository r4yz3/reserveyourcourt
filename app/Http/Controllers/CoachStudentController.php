<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Coach;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoachStudentController extends Controller
{
    private function paidLessons(Coach $coach): Builder
    {
        return $coach->bookings()->getQuery()->whereIn('status', ['confirmed', 'completed', 'no_show'])->where('total_centavos', '>', 0)->whereColumn('paid_centavos', 'total_centavos');
    }

    public function index(Request $request): View
    {
        $coach = $request->user()->coach()->firstOrFail();
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $students = $this->paidLessons($coach)->when($data['search'] ?? null, fn ($query, $search) => $query->where(fn ($match) => $match->where('customer_name', 'like', '%'.$search.'%')->orWhere('customer_contact', 'like', '%'.$search.'%')))
            ->select(['customer_name', 'customer_contact'])->selectRaw('MIN(id) AS student_booking_id, COUNT(*) AS lessons_count, SUM(CASE WHEN lesson_attendance = ? THEN 1 ELSE 0 END) AS attended_count, MAX(starts_at) AS latest_at', ['attended'])
            ->groupBy('customer_name', 'customer_contact')->orderByDesc('latest_at')->orderBy('customer_name')->orderBy('customer_contact')->paginate(20)->withQueryString();

        return view('coaches.students', compact('coach', 'students') + ['search' => $data['search'] ?? '']);
    }

    public function show(Request $request, Booking $booking): View
    {
        $coach = $request->user()->coach()->firstOrFail();
        abort_unless($booking->coach_id === $coach->id, 404);
        $paid = $this->paidLessons($coach)->where('customer_name', $booking->customer_name)->where('customer_contact', $booking->customer_contact);
        $summary = (clone $paid)->selectRaw('COUNT(*) AS lessons_count, SUM(CASE WHEN lesson_attendance = ? THEN 1 ELSE 0 END) AS attended_count, SUM(CASE WHEN lesson_attendance = ? THEN 1 ELSE 0 END) AS missed_count', ['attended', 'no_show'])->first();
        abort_unless((int) $summary->lessons_count > 0, 404);
        $history = $coach->bookings()->with(['court', 'business'])->where('customer_name', $booking->customer_name)->where('customer_contact', $booking->customer_contact)->orderByDesc('starts_at')->orderByDesc('id')->paginate(15)->withQueryString();

        return view('coaches.student',compact('coach','booking','summary','history'));
    }
}

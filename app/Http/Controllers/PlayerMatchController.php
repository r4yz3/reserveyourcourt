<?php

namespace App\Http\Controllers;

use App\Actions\PlayerStats;
use App\Actions\RecordPlayerMatch;
use App\Http\Requests\StorePlayerMatchRequest;
use App\Models\PlayerMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PlayerMatchController extends Controller
{
    public function index(Request $request, PlayerStats $stats): View
    {
        $user = $request->user();
        $matches = PlayerMatch::query()->with(['players', 'recorder'])->whereHas('players', fn (Builder $players) => $players->where('user_id', $user->id))->orderByDesc('played_on')->orderByDesc('id')->get();

        return view('play.scores', ['user' => $user, 'matches' => $matches, 'stats' => $stats->handle($user, $matches)]);
    }

    public function create(Request $request): View
    {
        $bookings = $request->user()->playerBookings()->with('business')->whereIn('status', ['confirmed', 'completed'])->where('starts_at', '<=', now())->orderByDesc('starts_at')->limit(20)->get();

        return view('play.record-score', ['bookings' => $bookings, 'selectedBooking' => $bookings->firstWhere('id', $request->integer('booking'))]);
    }

    public function store(StorePlayerMatchRequest $request, RecordPlayerMatch $record): RedirectResponse
    {
        $record->handle($request->user(), $request->validated());

        return redirect()->route('play.scores.index')->with('success', 'Score saved. Results count toward your record once an opponent confirms them.');
    }

    public function review(Request $request, PlayerMatch $match): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:confirm,dispute']]);
        DB::transaction(function () use ($request, $match, $data): void {
            $match = PlayerMatch::query()->with(['players', 'recorder'])->lockForUpdate()->findOrFail($match->id);
            abort_if($match->sideFor($request->user()) === null, 404);
            abort_unless($match->canBeReviewedBy($request->user()), 409, 'This score has already been reviewed.');
            $match->update(['status' => $data['decision'] === 'confirm' ? 'confirmed' : 'disputed', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        });

        return back()->with('success', $data['decision'] === 'confirm' ? 'Result confirmed. It now counts for both sides.' : 'Result marked as disputed. It will not count toward either record.');
    }

    public function destroy(Request $request, PlayerMatch $match): RedirectResponse
    {
        abort_unless($match->recorded_by === $request->user()->id, 404);
        abort_if($match->status === 'confirmed', 409, 'Confirmed results cannot be deleted.');
        $match->delete();

        return redirect()->route('play.scores.index')->with('success', 'Score removed.');
    }
}

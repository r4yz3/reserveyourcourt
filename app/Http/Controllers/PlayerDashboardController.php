<?php

namespace App\Http\Controllers;

use App\Actions\PlayerStats;
use App\Models\Booking;
use App\Models\PlayerMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerDashboardController extends Controller
{
    public function index(Request $request, PlayerStats $stats): View
    {
        $user = $request->user();
        $upcoming = $this->bookingQuery($user)->where('ends_at', '>', now())->orderBy('starts_at')->limit(5)->get();
        $matches = $this->matchesFor($user);
        $awaitingReview = $matches->filter(fn (PlayerMatch $match): bool => $match->canBeReviewedBy($user));

        return view('play.dashboard', ['user' => $user, 'upcoming' => $upcoming, 'recentMatches' => $matches->take(4), 'awaitingReview' => $awaitingReview, 'stats' => $stats->handle($user, $matches)]);
    }

    public function bookings(Request $request): View
    {
        $user = $request->user();

        return view('play.bookings', [
            'upcoming' => $this->bookingQuery($user)->where('ends_at', '>', now())->orderBy('starts_at')->get(),
            'past' => $this->bookingQuery($user)->where('ends_at', '<=', now())->orderByDesc('starts_at')->paginate(15),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('play.profile', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'skill_level' => ['nullable', 'in:2.0,2.5,3.0,3.5,4.0,4.5,5.0'], 'home_city' => ['nullable', 'string', 'max:120']]);
        $request->user()->update($data);

        return redirect()->route('play.profile')->with('success', 'Profile saved.');
    }

    /**
     * @return Builder<Booking>
     */
    private function bookingQuery(User $user): Builder
    {
        return Booking::query()->with(['business', 'court', 'coach.user'])->where('player_id', $user->id)->whereNotIn('status', ['expired']);
    }

    /**
     * @return Collection<int, PlayerMatch>
     */
    private function matchesFor(User $user): Collection
    {
        return PlayerMatch::query()->with(['players', 'recorder'])->whereHas('players', fn (Builder $players) => $players->where('user_id', $user->id))->orderByDesc('played_on')->orderByDesc('id')->get();
    }
}

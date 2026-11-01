<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOpenPlayRequest;
use App\Models\Business;
use App\Models\OpenPlay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HostedOpenPlayController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $counts = ['participants as joined_count' => fn (Builder $query) => $query->where('status', 'joined')];

        return view('play.events', [
            'hosting' => $user->hostedOpenPlays()->with('business')->withCount($counts)->where('ends_at', '>', now())->whereIn('status', ['draft', 'published'])->orderBy('starts_at')->get(),
            'joined' => OpenPlay::query()->with(['business', 'participants' => fn ($query) => $query->where('user_id', $user->id)])->withCount($counts)
                ->whereHas('participants', fn (Builder $query) => $query->where('user_id', $user->id)->whereIn('status', ['joined', 'waitlisted']))
                ->where('ends_at', '>', now())->orderBy('starts_at')->get(),
            'past' => OpenPlay::query()->with('business')->where('ends_at', '<=', now())->where('status', 'published')
                ->where(fn (Builder $query) => $query->where('host_id', $user->id)->orWhereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->id)->where('status', 'joined')))
                ->orderByDesc('starts_at')->limit(10)->get(),
        ]);
    }

    public function create(): View
    {
        return view('play.event-form', ['openPlay' => new OpenPlay(['privacy' => 'public', 'capacity' => 8, 'format' => 'doubles', 'gender_rule' => 'any']), 'venues' => $this->venues()]);
    }

    public function store(StoreOpenPlayRequest $request): RedirectResponse
    {
        $openPlay = $request->user()->hostedOpenPlays()->create($request->openPlayAttributes() + [
            'invite_token' => Str::random(40),
            'passkey_hash' => $request->input('privacy') === 'private' && $request->filled('passkey') ? Hash::make($request->input('passkey')) : null,
            'status' => $request->input('intent') === 'publish' ? 'published' : 'draft',
        ]);

        return redirect()->route('open-play.show', $openPlay)->with('success', $openPlay->status === 'published' ? 'Open play published. Share the link to fill your spots.' : 'Draft saved. Publish it when you are ready for players to join.');
    }

    public function edit(Request $request, OpenPlay $openPlay): View
    {
        abort_unless($openPlay->host_id === $request->user()->id, 404);
        abort_if($openPlay->status === 'cancelled', 409, 'Cancelled open plays cannot be edited.');

        return view('play.event-form', ['openPlay' => $openPlay, 'venues' => $this->venues()]);
    }

    public function update(StoreOpenPlayRequest $request, OpenPlay $openPlay): RedirectResponse
    {
        abort_if($openPlay->status === 'cancelled', 409, 'Cancelled open plays cannot be edited.');
        $passkey = match (true) {
            $request->input('privacy') !== 'private', $request->boolean('remove_passkey') => null,
            $request->filled('passkey') => Hash::make($request->input('passkey')),
            default => $openPlay->passkey_hash,
        };
        $openPlay->update($request->openPlayAttributes() + [
            'passkey_hash' => $passkey,
            'status' => $openPlay->status === 'published' || $request->input('intent') === 'publish' ? 'published' : 'draft',
        ]);

        return redirect()->route('open-play.show', $openPlay)->with('success', 'Open play updated.');
    }

    public function cancel(Request $request, OpenPlay $openPlay): RedirectResponse
    {
        abort_unless($openPlay->host_id === $request->user()->id, 404);
        $openPlay->update(['status' => 'cancelled']);

        return redirect()->route('play.events')->with('success', 'Open play cancelled. Joined players will see it as cancelled.');
    }

    /**
     * @return Collection<int, Business>
     */
    private function venues()
    {
        return Business::query()->where('status', 'approved')->where('is_demo', false)->orderBy('name')->get(['id', 'name', 'city']);
    }
}

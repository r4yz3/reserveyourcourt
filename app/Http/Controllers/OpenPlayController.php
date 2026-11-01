<?php

namespace App\Http\Controllers;

use App\Actions\JoinOpenPlay;
use App\Models\OpenPlay;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OpenPlayController extends Controller
{
    /**
     * Public discovery lists only published, public, upcoming events.
     */
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'format' => ['nullable', 'in:doubles,singles,mixed_rotation'], 'date' => ['nullable', 'date_format:Y-m-d']]);
        $events = OpenPlay::query()->with(['business', 'host'])->withCount(['participants as joined_count' => fn (Builder $query) => $query->where('status', 'joined')])
            ->where('status', 'published')->where('privacy', 'public')->where('starts_at', '>', now())
            ->when($data['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner->where('title', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")->orWhereHas('business', fn (Builder $business) => $business->where('name', 'like', "%{$search}%")->orWhere('city', 'like', "%{$search}%"))))
            ->when($data['format'] ?? null, fn (Builder $query, string $format) => $query->where('format', $format))
            ->when($data['date'] ?? null, fn (Builder $query, string $date) => $query->whereBetween('starts_at', [now()->parse($date, 'Asia/Manila')->startOfDay()->utc(), now()->parse($date, 'Asia/Manila')->endOfDay()->utc()]))
            ->orderBy('starts_at')->paginate(12)->withQueryString();

        return view('open-play.index', compact('events'));
    }

    public function show(Request $request, OpenPlay $openPlay): View
    {
        $user = $request->user();
        $invite = $request->query('invite');
        abort_unless($this->canView($openPlay, $user, is_string($invite) ? $invite : null), 404);
        $openPlay->load(['business', 'club', 'host', 'participants' => fn ($query) => $query->whereIn('status', ['joined', 'waitlisted'])->with('user')]);
        if (! $user) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('open-play.show', [
            'openPlay' => $openPlay,
            'user' => $user,
            'invite' => $openPlay->privacy === 'private' && is_string($invite) ? $invite : null,
            'entry' => $user ? $openPlay->participants->firstWhere('user_id', $user->id) : null,
            'joined' => $openPlay->participants->where('status', 'joined')->values(),
            'waitlist' => $openPlay->participants->where('status', 'waitlisted')->values(),
            'problem' => $user && $user->id !== $openPlay->host_id ? $openPlay->eligibilityProblem($user) : null,
            'isHost' => $user?->id === $openPlay->host_id,
        ]);
    }

    public function join(Request $request, OpenPlay $openPlay, JoinOpenPlay $joinOpenPlay): RedirectResponse
    {
        $data = $request->validate(['invite' => ['nullable', 'string', 'max:64'], 'passkey' => ['nullable', 'string', 'max:32']]);
        abort_unless($this->canView($openPlay, $request->user(), $data['invite'] ?? null), 404);
        if ($openPlay->privacy === 'private' && $openPlay->passkey_hash && ! Hash::check((string) ($data['passkey'] ?? ''), $openPlay->passkey_hash)) {
            throw ValidationException::withMessages(['passkey' => 'That passkey does not match. Ask the host for the current passkey.']);
        }
        $entry = $joinOpenPlay->join($openPlay, $request->user());

        return redirect($this->eventUrl($openPlay, $data['invite'] ?? null))->with('success', $entry->status === 'joined' ? 'You are in. See you on court.' : 'This open play is full. You are on the waitlist and will move up automatically if a spot opens.');
    }

    public function leave(Request $request, OpenPlay $openPlay, JoinOpenPlay $joinOpenPlay): RedirectResponse
    {
        $joinOpenPlay->leave($openPlay, $request->user());

        return redirect($this->eventUrl($openPlay, $request->input('invite')))->with('success', 'You have left this open play.');
    }

    /**
     * Private events open with the invite link; anyone already in the event, and the host, can always return.
     */
    private function canView(OpenPlay $openPlay, ?User $user, ?string $invite): bool
    {
        if ($user && $openPlay->host_id === $user->id) {
            return true;
        }
        if ($openPlay->status === 'draft') {
            return false;
        }
        if ($user && $openPlay->participants()->where('user_id', $user->id)->whereIn('status', ['joined', 'waitlisted'])->exists()) {
            return true;
        }
        if ($openPlay->status !== 'published') {
            return false;
        }

        if ($openPlay->privacy === 'club') {
            return $openPlay->club?->isMember($user) ?? false;
        }

        return $openPlay->privacy === 'public' || ($invite !== null && hash_equals($openPlay->invite_token, $invite));
    }

    private function eventUrl(OpenPlay $openPlay, mixed $invite): string
    {
        return route('open-play.show', array_filter(['openPlay' => $openPlay, 'invite' => $openPlay->privacy === 'private' && is_string($invite) ? $invite : null]));
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\OptimizeImage;
use App\Http\Requests\StoreClubRequest;
use App\Http\Requests\UpdatePlayerProfileRequest;
use App\Models\Club;
use App\Models\OpenPlay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClubController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:80'], 'tab' => ['nullable', 'in:mine,discover']]);
        $user = $request->user();
        $tab = $data['tab'] ?? ($user && $user->clubMemberships()->exists() ? 'mine' : 'discover');
        $search = fn (Builder $query) => $query->when($data['search'] ?? null, fn (Builder $inner, string $term) => $inner->where(fn (Builder $match) => $match->where('name', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%")));

        $clubs = $tab === 'mine' && $user
            ? Club::query()->withCount('activeMemberships')->whereHas('memberships', fn (Builder $query) => $query->where('user_id', $user->id))->tap($search)->orderBy('name')->get()
            : Club::query()->withCount('activeMemberships')->where('is_public', true)->tap($search)->orderByDesc('active_memberships_count')->orderBy('name')->paginate(30)->withQueryString();

        return view('clubs.index', [
            'clubs' => $clubs,
            'tab' => $tab,
            'pendingIds' => $user ? $user->clubMemberships()->where('status', 'pending')->pluck('club_id')->all() : [],
        ]);
    }

    public function create(): View
    {
        return view('clubs.form', ['club' => new Club(['country' => 'Philippines', 'is_public' => true, 'auto_approve' => true]), 'countries' => UpdatePlayerProfileRequest::COUNTRIES]);
    }

    public function store(StoreClubRequest $request): RedirectResponse
    {
        $club = DB::transaction(function () use ($request): Club {
            $club = Club::query()->create($request->validated() + ['owner_id' => $request->user()->id, 'invite_token' => Str::random(40)]);
            $club->memberships()->create(['user_id' => $request->user()->id, 'role' => 'owner', 'status' => 'active']);

            return $club;
        });

        return redirect()->route('clubs.show', $club)->with('success', 'Club created. Add a logo and cover in settings, then share your invite link.');
    }

    public function show(Request $request, Club $club): View
    {
        $user = $request->user();
        abort_unless($club->isVisibleTo($user), 404);
        $tab = in_array($request->query('tab'), ['feed', 'about', 'members'], true) ? $request->query('tab') : 'feed';
        $membership = $club->membershipFor($user);
        $isMember = $membership?->status === 'active';
        $isManager = $isMember && in_array($membership->role, ['owner', 'moderator'], true);

        return view('clubs.show', [
            'club' => $club->loadCount('activeMemberships'),
            'tab' => $tab,
            'membership' => $membership,
            'isMember' => $isMember,
            'isManager' => $isManager,
            'canPost' => $club->canPost($user),
            'openPlays' => OpenPlay::query()->with('business')->withCount(['participants as joined_count' => fn (Builder $query) => $query->where('status', 'joined')])
                ->where('club_id', $club->id)->where('status', 'published')->where('starts_at', '>', now())
                ->when(! $isMember, fn (Builder $query) => $query->where('privacy', 'public'))->orderBy('starts_at')->limit(6)->get(),
            'posts' => $isMember ? $club->posts()->with('author')->orderByDesc('is_pinned')->latest()->paginate(15)->appends(['tab' => 'feed']) : null,
            'members' => $isMember ? $club->activeMemberships()->with('user')->orderByRaw("case role when 'owner' then 0 when 'moderator' then 1 else 2 end")->orderBy('created_at')->get() : collect(),
            'pending' => $isManager ? $club->memberships()->with('user')->where('status', 'pending')->orderBy('created_at')->get() : collect(),
            'inviteUrl' => $isMember ? route('clubs.invite', $club->invite_token) : null,
        ]);
    }

    public function edit(Request $request, Club $club): View
    {
        abort_unless($club->isManager($request->user()), 404);

        return view('clubs.form', ['club' => $club, 'countries' => UpdatePlayerProfileRequest::COUNTRIES]);
    }

    public function update(StoreClubRequest $request, Club $club): RedirectResponse
    {
        $club->update($request->validated());

        return redirect()->route('clubs.show', ['club' => $club, 'tab' => 'about'])->with('success', 'Club settings saved.');
    }

    public function resetInvite(Request $request, Club $club): RedirectResponse
    {
        abort_unless($club->isManager($request->user()), 404);
        $club->update(['invite_token' => Str::random(40)]);

        return redirect()->route('clubs.show', ['club' => $club, 'tab' => 'members'])->with('success', 'New invite link created. The old link no longer works.');
    }

    public function storeMedia(Request $request, Club $club, string $kind, OptimizeImage $images): RedirectResponse
    {
        abort_unless($club->isManager($request->user()), 404);
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000,min_width=160,min_height=160']]);
        $image = $images->encode($request->file('image'), $kind === 'logo' ? 512 : 1920, 82, 'image');
        $disk = config('filesystems.media_disk');
        $path = 'club-media/'.$club->id.'/'.$kind.'-'.Str::uuid().'.webp';
        if (! Storage::disk($disk)->put($path, $image['bytes'])) {
            throw new \RuntimeException('Club image storage failed.');
        }
        $column = $kind.'_path';
        $previous = [$club->media_disk, $club->{$column}];
        $club->update([$column => $path, 'media_disk' => $disk]);
        if ($previous[0] && $previous[1]) {
            Storage::disk($previous[0])->delete($previous[1]);
        }

        return redirect()->route('clubs.edit', $club)->with('success', $kind === 'logo' ? 'Logo updated.' : 'Cover updated.');
    }

    public function media(Request $request, Club $club, string $kind): StreamedResponse
    {
        $path = $club->{$kind.'_path'};
        abort_unless($path && $club->media_disk && $club->isVisibleTo($request->user()), 404);

        return Storage::disk($club->media_disk)->response($path, $kind.'.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => $club->is_public ? 'public, max-age=3600' : 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}

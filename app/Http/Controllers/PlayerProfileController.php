<?php

namespace App\Http\Controllers;

use App\Actions\OptimizeImage;
use App\Actions\PlayerStats;
use App\Http\Requests\UpdatePlayerProfileRequest;
use App\Models\OpenPlay;
use App\Models\PlayerMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlayerProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('play.profile', ['user' => $request->user(), 'countries' => UpdatePlayerProfileRequest::COUNTRIES]);
    }

    public function update(UpdatePlayerProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return redirect()->route('play.profile')->with('success', 'Profile saved.');
    }

    /**
     * Saves the completion dialog. Only matching details are accepted here.
     */
    public function complete(UpdatePlayerProfileRequest $request): RedirectResponse
    {
        $request->user()->update(array_filter($request->validated(), fn ($value): bool => $value !== null) + ['profile_prompt_dismissed_at' => now()]);

        return back()->with('success', 'Profile updated. Thanks — this helps with open play matching.');
    }

    public function dismiss(Request $request): RedirectResponse
    {
        $request->user()->update(['profile_prompt_dismissed_at' => now()]);

        return back();
    }

    public function storePhoto(Request $request, OptimizeImage $images): RedirectResponse
    {
        $request->validate(['photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=8000,max_height=8000,min_width=120,min_height=120']]);
        $image = $images->encode($request->file('photo'), 600, 82, 'photo');
        $disk = config('filesystems.media_disk');
        $path = 'player-photos/'.$request->user()->id.'/'.Str::uuid().'.webp';
        if (! Storage::disk($disk)->put($path, $image['bytes'])) {
            throw new \RuntimeException('Player photo storage failed.');
        }
        $user = $request->user();
        $previous = [$user->photo_disk, $user->photo_path];
        $user->update(['photo_path' => $path, 'photo_disk' => $disk]);
        $this->removePhoto($previous);

        return redirect()->route('play.profile')->with('success', 'Photo updated.');
    }

    public function destroyPhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $previous = [$user->photo_disk, $user->photo_path];
        $user->update(['photo_path' => null, 'photo_disk' => null]);
        $this->removePhoto($previous);

        return redirect()->route('play.profile')->with('success', 'Photo removed.');
    }

    public function photo(Request $request, User $user): StreamedResponse
    {
        abort_unless($user->photo_path && $user->photo_disk && ($user->show_profile || $request->user()->is($user)), 404);

        return Storage::disk($user->photo_disk)->response($user->photo_path, 'player.webp', ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * The player card other signed-in players see. Contact details and address are never shown.
     */
    public function show(Request $request, User $user, PlayerStats $stats): View
    {
        $isOwner = $request->user()->is($user);
        abort_unless($user->show_profile || $isOwner, 404);
        $matches = PlayerMatch::query()->with(['players', 'recorder'])->whereHas('players', fn (Builder $players) => $players->where('user_id', $user->id))->orderByDesc('played_on')->get();
        $played = OpenPlay::query()->where('status', 'published')->where('ends_at', '<', now())->whereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->id)->where('status', 'joined'))->count();
        $activity = OpenPlay::query()->with('business')->where('status', 'published')->where('privacy', 'public')
            ->where(fn (Builder $query) => $query->where('host_id', $user->id)->orWhereHas('participants', fn (Builder $participants) => $participants->where('user_id', $user->id)->where('status', 'joined')))
            ->orderByDesc('starts_at')->limit(6)->get();

        return view('players.show', [
            'player' => $user,
            'isOwner' => $isOwner,
            'showStats' => $isOwner || $user->show_stats,
            'showRating' => $isOwner || $user->show_rating,
            'showLocation' => $isOwner || $user->show_location,
            'record' => $stats->handle($user, $matches),
            'played' => $played,
            'clubs' => $user->clubMemberships()->where('status', 'active')->whereHas('club', fn (Builder $club) => $club->where('is_public', true))->count(),
            'organized' => $user->hostedOpenPlays()->where('status', 'published')->count(),
            'activity' => $isOwner || $user->show_activity ? $activity : collect(),
        ]);
    }

    /**
     * @param  array{0: ?string, 1: ?string}  $previous
     */
    private function removePhoto(array $previous): void
    {
        if ($previous[0] && $previous[1]) {
            Storage::disk($previous[0])->delete($previous[1]);
        }
    }
}

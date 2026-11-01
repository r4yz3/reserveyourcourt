<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClubPostController extends Controller
{
    public function store(Request $request, Club $club): RedirectResponse
    {
        abort_unless($club->isMember($request->user()), 404);
        abort_unless($club->canPost($request->user()), 403, 'Only club managers can post in this club.');
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $club->posts()->create(['user_id' => $request->user()->id, 'body' => trim($data['body'])]);

        return redirect()->route('clubs.show', $club)->with('success', 'Posted to the club feed.');
    }

    public function pin(Request $request, Club $club, ClubPost $post): RedirectResponse
    {
        abort_unless($post->club_id === $club->id && $club->isManager($request->user()), 404);
        $post->update(['is_pinned' => ! $post->is_pinned]);

        return redirect()->route('clubs.show', $club)->with('success', $post->is_pinned ? 'Post pinned to the top.' : 'Post unpinned.');
    }

    public function destroy(Request $request, Club $club, ClubPost $post): RedirectResponse
    {
        abort_unless($post->club_id === $club->id && ($post->user_id === $request->user()->id || $club->isManager($request->user())), 404);
        $post->delete();

        return redirect()->route('clubs.show', $club)->with('success', 'Post removed.');
    }
}

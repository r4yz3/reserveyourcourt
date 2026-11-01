<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClubMembershipController extends Controller
{
    /**
     * Public clubs accept join requests from anyone; private clubs only through the invite link.
     */
    public function join(Request $request, Club $club): RedirectResponse
    {
        abort_unless($club->is_public, 404);
        $membership = $this->addMember($club, $request->user()->id, $club->auto_approve ? 'active' : 'pending');

        return redirect()->route('clubs.show', $club)->with('success', $membership->status === 'active' ? 'Welcome to '.$club->name.'.' : 'Join request sent. A club manager will review it.');
    }

    public function invite(Request $request, string $token): View|RedirectResponse
    {
        $club = Club::query()->where('invite_token', $token)->firstOrFail();
        if ($club->isMember($request->user())) {
            return redirect()->route('clubs.show', $club);
        }
        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('clubs.invite', ['club' => $club->loadCount('activeMemberships'), 'token' => $token]);
    }

    /**
     * An invite link is the club's own approval, so it skips the review queue.
     */
    public function acceptInvite(Request $request, string $token): RedirectResponse
    {
        $club = Club::query()->where('invite_token', $token)->firstOrFail();
        $this->addMember($club, $request->user()->id, 'active');

        return redirect()->route('clubs.show', $club)->with('success', 'Welcome to '.$club->name.'.');
    }

    public function leave(Request $request, Club $club): RedirectResponse
    {
        $membership = $club->memberships()->where('user_id', $request->user()->id)->firstOrFail();
        abort_if($membership->role === 'owner', 409, 'Owners cannot leave their own club.');
        $membership->delete();

        return redirect()->route('clubs.index')->with('success', $membership->status === 'pending' ? 'Join request withdrawn.' : 'You have left '.$club->name.'.');
    }

    public function update(Request $request, Club $club, ClubMember $member): RedirectResponse
    {
        abort_unless($member->club_id === $club->id && $club->isManager($request->user()), 404);
        $data = $request->validate(['action' => ['required', 'in:approve,decline,promote,demote,remove']]);
        $isOwner = $club->owner_id === $request->user()->id;
        abort_if($member->role === 'owner', 409, 'The club owner cannot be changed here.');
        abort_if(in_array($data['action'], ['promote', 'demote'], true) && ! $isOwner, 403, 'Only the owner can change moderator roles.');
        abort_if($member->role === 'moderator' && $data['action'] === 'remove' && ! $isOwner, 403, 'Only the owner can remove a moderator.');

        match ($data['action']) {
            'approve' => $member->update(['status' => 'active']),
            'decline', 'remove' => $member->delete(),
            'promote' => $member->update(['role' => 'moderator']),
            'demote' => $member->update(['role' => 'member']),
        };

        return redirect()->route('clubs.show', ['club' => $club, 'tab' => 'members'])->with('success', [
            'approve' => 'Member approved.', 'decline' => 'Request declined.', 'remove' => 'Member removed.', 'promote' => 'Now a moderator.', 'demote' => 'Moderator role removed.',
        ][$data['action']]);
    }

    private function addMember(Club $club, int $userId, string $status): ClubMember
    {
        return DB::transaction(function () use ($club, $userId, $status): ClubMember {
            $existing = $club->memberships()->where('user_id', $userId)->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status === 'pending' && $status === 'active') {
                    $existing->update(['status' => 'active']);
                }

                return $existing;
            }

            return $club->memberships()->create(['user_id' => $userId, 'role' => 'member', 'status' => $status]);
        });
    }
}

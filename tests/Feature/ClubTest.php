<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\OpenPlay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ClubTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
    }

    private function player(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['gender' => 'female', 'contact_number' => '09171234567', 'home_city' => 'Test City']);
    }

    private function member(Club $club, string $role = 'member', string $status = 'active'): User
    {
        $user = $this->player();
        $club->memberships()->create(['user_id' => $user->id, 'role' => $role, 'status' => $status]);

        return $user;
    }

    public function test_creating_a_club_makes_the_creator_its_owner(): void
    {
        $owner = $this->player();
        $this->actingAs($owner)->post(route('clubs.store'), ['name' => 'Test Sunday Dinkers', 'country' => 'Philippines', 'city' => 'Test City', 'is_public' => '1', 'facebook_url' => 'https://facebook.com/testclub'])->assertSessionHasNoErrors();

        $club = Club::query()->sole();
        $this->assertSame([$owner->id, true, false, false], [$club->owner_id, $club->is_public, $club->auto_approve, $club->members_can_post]);
        $this->assertSame('owner', $club->memberships()->sole()->role);
        $this->assertTrue($club->isManager($owner));
    }

    public function test_club_links_must_be_safe_https_urls(): void
    {
        $this->actingAs($this->player())->post(route('clubs.store'), ['name' => 'Test Club', 'country' => 'Philippines', 'facebook_url' => 'javascript:alert(1)', 'website_url' => 'http://example.test'])
            ->assertSessionHasErrors(['facebook_url', 'website_url']);
        $this->actingAs($this->player())->post(route('clubs.store'), ['name' => 'Test Club', 'country' => 'Philippines', 'facebook_url' => 'https://evil.test/facebook.com/'])->assertSessionHasErrors('facebook_url');
    }

    public function test_discover_lists_public_clubs_and_my_clubs_lists_memberships(): void
    {
        $public = Club::factory()->create(['name' => 'Test Public Club']);
        Club::factory()->create(['name' => 'Test Hidden Club', 'is_public' => false]);
        $player = $this->player();

        $this->get(route('clubs.index', ['tab' => 'discover']))->assertOk()->assertSee('Test Public Club')->assertDontSee('Test Hidden Club');
        $this->actingAs($player)->get(route('clubs.index', ['tab' => 'mine']))->assertOk()->assertSee('You are not in any clubs yet.');
        $public->memberships()->create(['user_id' => $player->id, 'role' => 'member', 'status' => 'active']);
        $this->actingAs($player)->get(route('clubs.index', ['tab' => 'mine']))->assertOk()->assertSee('Test Public Club');
        $this->actingAs($player)->get(route('clubs.index', ['tab' => 'discover', 'search' => 'nothing-matches']))->assertSee('No clubs match');
    }

    public function test_auto_approve_joins_immediately_otherwise_requests_wait_for_a_manager(): void
    {
        $open = Club::factory()->create();
        $reviewed = Club::factory()->create(['auto_approve' => false]);
        $player = $this->player();

        $this->actingAs($player)->post(route('clubs.join', $open))->assertRedirect();
        $this->assertTrue($open->isMember($player));

        $this->actingAs($player)->post(route('clubs.join', $reviewed));
        $request = $reviewed->memberships()->where('user_id', $player->id)->sole();
        $this->assertSame('pending', $request->status);
        $this->assertFalse($reviewed->isMember($player));

        $this->actingAs($this->member($reviewed))->patch(route('clubs.members.update', [$reviewed, $request]), ['action' => 'approve'])->assertNotFound();
        $this->actingAs($reviewed->owner)->patch(route('clubs.members.update', [$reviewed, $request]), ['action' => 'approve'])->assertRedirect();
        $this->assertTrue($reviewed->isMember($player));
    }

    public function test_private_clubs_are_hidden_and_joined_only_by_invite(): void
    {
        $club = Club::factory()->create(['name' => 'Test Secret Club', 'is_public' => false, 'auto_approve' => false]);
        $player = $this->player();

        $this->actingAs($player)->get(route('clubs.show', $club))->assertNotFound();
        $this->actingAs($player)->post(route('clubs.join', $club))->assertNotFound();
        $this->actingAs($player)->get(route('clubs.invite', 'wrong-token'))->assertNotFound();
        $this->actingAs($player)->get(route('clubs.invite', $club->invite_token))->assertOk()->assertSee('Test Secret Club');
        $this->actingAs($player)->post(route('clubs.invite.accept', $club->invite_token))->assertRedirect(route('clubs.show', $club));

        $this->assertTrue($club->isMember($player));
        $this->actingAs($player)->get(route('clubs.show', $club))->assertOk();
    }

    public function test_resetting_the_invite_link_disables_the_old_one(): void
    {
        $club = Club::factory()->create();
        $old = $club->invite_token;
        $this->actingAs($this->member($club))->post(route('clubs.invite.reset', $club))->assertNotFound();
        $this->actingAs($club->owner)->post(route('clubs.invite.reset', $club))->assertRedirect();

        $this->actingAs($this->player())->get(route('clubs.invite', $old))->assertNotFound();
        $this->assertNotSame($old, $club->fresh()->invite_token);
    }

    public function test_feed_and_members_are_for_members_only(): void
    {
        $club = Club::factory()->create();
        $member = $this->member($club);
        $club->posts()->create(['user_id' => $club->owner_id, 'body' => 'Test members-only update']);

        $this->get(route('clubs.show', $club))->assertOk()->assertDontSee('Test members-only update')->assertSee('The feed is for members.');
        $this->actingAs($this->player())->get(route('clubs.show', ['club' => $club, 'tab' => 'members']))->assertDontSee($club->invite_token)->assertSee('Members are visible to members.');
        $this->actingAs($member)->get(route('clubs.show', $club))->assertSee('Test members-only update');
        $this->actingAs($member)->get(route('clubs.show', ['club' => $club, 'tab' => 'members']))->assertSee($club->invite_token);
    }

    public function test_posting_follows_the_members_can_post_setting(): void
    {
        $club = Club::factory()->create(['members_can_post' => false]);
        $member = $this->member($club);

        $this->actingAs($member)->post(route('clubs.posts.store', $club), ['body' => 'Test post'])->assertForbidden();
        $this->actingAs($this->player())->post(route('clubs.posts.store', $club), ['body' => 'Test post'])->assertNotFound();
        $this->actingAs($club->owner)->post(route('clubs.posts.store', $club), ['body' => 'Test owner post'])->assertRedirect();

        $club->update(['members_can_post' => true]);
        $this->actingAs($member)->post(route('clubs.posts.store', $club), ['body' => 'Test member post'])->assertRedirect();
        $this->assertSame(2, $club->posts()->count());
    }

    public function test_only_authors_and_managers_delete_posts_and_only_managers_pin(): void
    {
        $club = Club::factory()->create(['members_can_post' => true]);
        $author = $this->member($club);
        $other = $this->member($club);
        $post = $club->posts()->create(['user_id' => $author->id, 'body' => 'Test post']);

        $this->actingAs($other)->delete(route('clubs.posts.destroy', [$club, $post]))->assertNotFound();
        $this->actingAs($author)->patch(route('clubs.posts.pin', [$club, $post]))->assertNotFound();
        $this->actingAs($club->owner)->patch(route('clubs.posts.pin', [$club, $post]))->assertRedirect();
        $this->assertTrue($post->fresh()->is_pinned);
        $this->actingAs($author)->delete(route('clubs.posts.destroy', [$club, $post]))->assertRedirect();
        $this->assertModelMissing($post);
    }

    public function test_only_the_owner_manages_moderators_and_the_owner_cannot_be_removed(): void
    {
        $club = Club::factory()->create();
        $moderator = $this->member($club, 'moderator');
        $member = $this->member($club);
        $memberEntry = $club->memberships()->where('user_id', $member->id)->sole();
        $ownerEntry = $club->memberships()->where('role', 'owner')->sole();

        $this->actingAs($moderator)->patch(route('clubs.members.update', [$club, $memberEntry]), ['action' => 'promote'])->assertForbidden();
        $this->actingAs($moderator)->patch(route('clubs.members.update', [$club, $ownerEntry]), ['action' => 'remove'])->assertStatus(409);
        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $memberEntry]), ['action' => 'promote'])->assertRedirect();
        $this->assertSame('moderator', $memberEntry->fresh()->role);
        $this->actingAs($moderator)->patch(route('clubs.members.update', [$club, $memberEntry]), ['action' => 'remove'])->assertForbidden();
        $this->actingAs($club->owner)->post(route('clubs.leave', $club))->assertStatus(409);
    }

    public function test_actions_only_apply_to_the_matching_membership_state(): void
    {
        $club = Club::factory()->create(['auto_approve' => false]);
        $moderator = $this->member($club, 'moderator');
        $otherModerator = $club->memberships()->where('user_id', $this->member($club, 'moderator')->id)->sole();
        $pending = $club->memberships()->where('user_id', $this->member($club, 'member', 'pending')->id)->sole();

        $this->actingAs($moderator)->patch(route('clubs.members.update', [$club, $otherModerator]), ['action' => 'decline'])->assertStatus(409);
        $this->actingAs($moderator)->patch(route('clubs.members.update', [$club, $pending]), ['action' => 'promote'])->assertStatus(403);
        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $pending]), ['action' => 'promote'])->assertStatus(409);
        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $pending]), ['action' => 'remove'])->assertStatus(409);
        $this->assertModelExists($otherModerator);
        $this->assertSame(['moderator', 'member'], [$otherModerator->fresh()->role, $pending->fresh()->role]);
    }

    public function test_role_and_approval_actions_are_rejected_when_already_applied(): void
    {
        $club = Club::factory()->create();
        $moderatorEntry = $club->memberships()->where('user_id', $this->member($club, 'moderator')->id)->sole();
        $memberEntry = $club->memberships()->where('user_id', $this->member($club)->id)->sole();

        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $moderatorEntry]), ['action' => 'promote'])->assertStatus(409);
        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $memberEntry]), ['action' => 'demote'])->assertStatus(409);
        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $memberEntry]), ['action' => 'approve'])->assertStatus(409);
        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $memberEntry]), ['action' => 'decline'])->assertStatus(409);
        $this->assertSame(['moderator', 'member'], [$moderatorEntry->fresh()->role, $memberEntry->fresh()->role]);
    }

    public function test_managers_can_still_decline_requests_remove_members_and_demote_moderators(): void
    {
        $club = Club::factory()->create(['auto_approve' => false]);
        $moderator = $this->member($club, 'moderator');
        $pending = $club->memberships()->where('user_id', $this->member($club, 'member', 'pending')->id)->sole();
        $memberEntry = $club->memberships()->where('user_id', $this->member($club)->id)->sole();
        $moderatorEntry = $club->memberships()->where('user_id', $moderator->id)->sole();

        $this->actingAs($moderator)->patch(route('clubs.members.update', [$club, $pending]), ['action' => 'decline'])->assertRedirect()->assertSessionHas('success', 'Request declined.');
        $this->assertModelMissing($pending);
        $this->actingAs($moderator)->patch(route('clubs.members.update', [$club, $memberEntry]), ['action' => 'remove'])->assertRedirect()->assertSessionHas('success', 'Member removed.');
        $this->assertModelMissing($memberEntry);
        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $moderatorEntry]), ['action' => 'demote'])->assertRedirect()->assertSessionHas('success', 'Moderator role removed.');
        $this->assertSame('member', $moderatorEntry->fresh()->role);
    }

    public function test_members_from_another_club_cannot_be_managed_through_this_one(): void
    {
        $club = Club::factory()->create();
        $other = Club::factory()->create();
        $stranger = $this->member($other);
        $entry = $other->memberships()->where('user_id', $stranger->id)->sole();

        $this->actingAs($club->owner)->patch(route('clubs.members.update', [$club, $entry]), ['action' => 'remove'])->assertNotFound();
        $this->assertModelExists($entry);
    }

    public function test_settings_are_for_managers_only(): void
    {
        $club = Club::factory()->create();
        $this->actingAs($this->member($club))->get(route('clubs.edit', $club))->assertNotFound();
        $this->actingAs($this->member($club))->put(route('clubs.update', $club), ['name' => 'Hijacked', 'country' => 'Philippines'])->assertForbidden();
        $this->actingAs($this->member($club, 'moderator'))->put(route('clubs.update', $club), ['name' => 'Test Renamed Club', 'country' => 'Philippines', 'is_public' => '1'])->assertRedirect();
        $this->assertSame('Test Renamed Club', $club->fresh()->name);
    }

    public function test_club_only_open_play_is_for_managers_to_host_and_members_to_see(): void
    {
        $club = Club::factory()->create();
        $member = $this->member($club);
        $payload = ['title' => 'Test club session', 'privacy' => 'club', 'club_id' => $club->id, 'location' => 'Test court', 'date' => '2026-11-05', 'start_time' => '08:00', 'duration' => 120, 'capacity' => 8, 'format' => 'doubles', 'gender_rule' => 'any', 'intent' => 'publish'];

        $this->actingAs($member)->post(route('play.events.store'), $payload)->assertSessionHasErrors('club_id');
        $this->actingAs($club->owner)->post(route('play.events.store'), array_replace($payload, ['club_id' => null]))->assertSessionHasErrors('club_id');
        $this->actingAs($club->owner)->post(route('play.events.store'), $payload)->assertSessionHasNoErrors();
        $event = OpenPlay::query()->sole();

        $this->get(route('open-play.index'))->assertDontSee('Test club session');
        $this->actingAs($this->player())->get(route('open-play.show', $event))->assertNotFound();
        $this->actingAs($member)->get(route('open-play.show', $event))->assertOk()->assertSee('Club members only');
        $this->actingAs($member)->post(route('open-play.join', $event))->assertSessionHasNoErrors();
        $this->actingAs($member)->get(route('clubs.show', $club))->assertSee('Test club session');
    }

    public function test_player_card_counts_public_clubs(): void
    {
        $player = $this->player();
        Club::factory()->create()->memberships()->create(['user_id' => $player->id, 'role' => 'member', 'status' => 'active']);
        Club::factory()->create(['is_public' => false])->memberships()->create(['user_id' => $player->id, 'role' => 'member', 'status' => 'active']);
        Club::factory()->create()->memberships()->create(['user_id' => $player->id, 'role' => 'member', 'status' => 'pending']);

        $response = $this->actingAs($this->player())->get(route('players.show', $player))->assertOk();
        $this->assertMatchesRegularExpression('/<dt>Clubs<\/dt><dd>1<\/dd>/', $response->getContent());
        $this->assertSame(3, ClubMember::query()->where('user_id', $player->id)->count());
    }
}

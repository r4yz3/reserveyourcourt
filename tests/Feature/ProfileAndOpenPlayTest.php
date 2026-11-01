<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\Court;
use App\Models\OpenPlay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAndOpenPlayTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
    }

    private function completePlayer(array $attributes = []): User
    {
        return User::factory()->create($attributes + ['gender' => 'female', 'contact_number' => '09171234567', 'home_city' => 'Test City', 'self_rating' => '3.25']);
    }

    private function eventPayload(array $overrides = []): array
    {
        return array_replace(['title' => 'Test open play', 'description' => 'Test notes.', 'privacy' => 'public', 'location' => 'Test court', 'date' => '2026-11-05', 'start_time' => '08:00', 'duration' => 120, 'capacity' => 4, 'format' => 'doubles', 'gender_rule' => 'any', 'fee' => '150', 'payment_note' => 'Cash at the court', 'intent' => 'publish'], $overrides);
    }

    public function test_full_profile_saves_details_and_visibility_switches(): void
    {
        $player = User::factory()->create();
        $this->actingAs($player)->put(route('play.profile.update'), [
            'name' => 'Test Player', 'alias' => 'Dinker', 'gender' => 'male', 'bio' => 'Test bio.', 'self_rating' => '3.75',
            'contact_number' => '+63 917 123 4567', 'country' => 'Philippines', 'home_city' => 'Test City', 'address' => 'Test street',
            'show_profile' => '1', 'show_rating' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('play.profile'));

        $player->refresh();
        $this->assertSame(['Dinker', 'male', '3.75', 'Dinker'], [$player->alias, $player->gender, $player->self_rating, $player->displayName()]);
        $this->assertSame([true, false, true, false, false], [$player->show_profile, $player->show_location, $player->show_rating, $player->show_activity, $player->show_stats]);
        $this->assertFalse($player->isProfileIncomplete());
    }

    public function test_profile_rejects_bad_values(): void
    {
        $player = User::factory()->create();
        $this->actingAs($player)->put(route('play.profile.update'), ['name' => 'Test', 'gender' => 'robot', 'contact_number' => 'call me', 'country' => 'Atlantis', 'bio' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['gender', 'contact_number', 'country', 'bio']);
    }

    public function test_completion_prompt_shows_until_saved_or_dismissed(): void
    {
        $player = User::factory()->create();
        $this->actingAs($player)->get(route('play.dashboard'))->assertSee('Complete your profile');

        $this->actingAs($player)->post(route('play.profile.dismiss'))->assertRedirect();
        $this->actingAs($player)->get(route('play.dashboard'))->assertDontSee('Complete your profile');

        $this->travel(8)->days();
        $this->actingAs($player)->get(route('play.dashboard'))->assertSee('Complete your profile');

        $this->actingAs($player)->post(route('play.profile.complete'), ['gender' => 'female', 'self_rating' => 'NR', 'contact_number' => '09171234567', 'country' => 'Philippines', 'home_city' => 'Test City'])->assertSessionHasNoErrors();
        $player->refresh();
        $this->assertNull($player->self_rating);
        $this->assertFalse($player->shouldPromptProfile());
        $this->actingAs($player)->post(route('play.profile.complete'), ['name' => 'Renamed'])->assertSessionHasErrors('name');
    }

    public function test_public_card_hides_private_details_and_respects_visibility(): void
    {
        $owner = $this->completePlayer(['name' => 'Test Full Name', 'alias' => 'TestAlias', 'address' => 'Secret Street', 'bio' => 'Test bio.', 'self_rating' => '3.40']);
        $viewer = $this->completePlayer();

        $this->actingAs($viewer)->get(route('players.show', $owner))->assertOk()->assertSee('TestAlias')->assertSee('3.40')->assertSee('Test City')
            ->assertDontSee('Test Full Name')->assertDontSee('09171234567')->assertDontSee('Secret Street')->assertDontSee('Edit profile');

        $owner->update(['show_rating' => false, 'show_location' => false]);
        $this->actingAs($viewer)->get(route('players.show', $owner))->assertOk()->assertDontSee('3.40')->assertDontSee('Test City');
        $this->actingAs($owner)->get(route('players.show', $owner))->assertOk()->assertSee('Edit profile')->assertSee('3.40');

        $owner->update(['show_profile' => false]);
        $this->actingAs($viewer)->get(route('players.show', $owner))->assertNotFound();
        $this->post(route('logout'));
        $this->get(route('players.show', $owner))->assertRedirect(route('login'));
    }

    public function test_photo_upload_is_optimized_and_served_only_when_visible(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP support is required for image optimization.');
        }
        Storage::fake('local');
        $player = User::factory()->create();
        $this->actingAs($player)->post(route('play.profile.photo'), ['photo' => UploadedFile::fake()->image('me.jpg', 800, 800)])->assertRedirect(route('play.profile'));
        $player->refresh();
        Storage::disk('local')->assertExists($player->photo_path);
        $this->actingAs(User::factory()->create())->get(route('players.photo', $player))->assertOk();
        $player->update(['show_profile' => false]);
        $this->actingAs(User::factory()->create())->get(route('players.photo', $player))->assertNotFound();
    }

    public function test_host_publishes_an_event_that_appears_in_discovery(): void
    {
        $host = $this->completePlayer();
        $this->actingAs($host)->post(route('play.events.store'), $this->eventPayload())->assertSessionHasNoErrors();
        $event = OpenPlay::query()->sole();

        $this->assertSame(['published', 15000, 'Test court'], [$event->status, $event->fee_centavos, $event->location]);
        $this->assertSame('2026-11-05 00:00:00', $event->starts_at->utc()->format('Y-m-d H:i:s'));
        $this->post(route('logout'));
        $this->get(route('open-play.index'))->assertOk()->assertSee('Test open play')->assertSee('4 of 4 spots left');
        $this->get(route('open-play.show', $event))->assertOk()->assertSee('Sign in to join');
    }

    public function test_drafts_and_private_events_stay_out_of_discovery(): void
    {
        $host = $this->completePlayer();
        $draft = OpenPlay::factory()->create(['host_id' => $host->id, 'title' => 'Test Draft Event', 'status' => 'draft']);
        $private = OpenPlay::factory()->create(['host_id' => $host->id, 'title' => 'Test Private Event', 'privacy' => 'private']);
        $viewer = User::factory()->create();

        $this->get(route('open-play.index'))->assertDontSee('Test Draft Event')->assertDontSee('Test Private Event');
        $this->actingAs($viewer)->get(route('open-play.show', $draft))->assertNotFound();
        $this->actingAs($viewer)->get(route('open-play.show', $private))->assertNotFound();
        $this->actingAs($viewer)->get(route('open-play.show', ['openPlay' => $private, 'invite' => 'wrong-token']))->assertNotFound();
        $this->actingAs($viewer)->get(route('open-play.show', ['openPlay' => $private, 'invite' => $private->invite_token]))->assertOk()->assertSee('Test Private Event');
        $this->actingAs($host)->get(route('open-play.show', $draft))->assertOk()->assertSee('Draft');
    }

    public function test_private_event_join_needs_the_link_and_passkey(): void
    {
        $event = OpenPlay::factory()->create(['privacy' => 'private', 'passkey_hash' => Hash::make('test-pass')]);
        $player = $this->completePlayer();

        $this->actingAs($player)->post(route('open-play.join', $event))->assertNotFound();
        $this->actingAs($player)->post(route('open-play.join', $event), ['invite' => $event->invite_token, 'passkey' => 'wrong'])->assertSessionHasErrors('passkey');
        $this->actingAs($player)->post(route('open-play.join', $event), ['invite' => $event->invite_token, 'passkey' => 'test-pass'])->assertSessionHasNoErrors();
        $this->assertSame('joined', $event->participants()->sole()->status);
        $this->actingAs($player)->get(route('open-play.show', $event))->assertOk()->assertSee('You are in.');
    }

    public function test_full_events_waitlist_and_promote_when_a_spot_opens(): void
    {
        $event = OpenPlay::factory()->create(['capacity' => 2]);
        [$first, $second, $third] = [$this->completePlayer(), $this->completePlayer(), $this->completePlayer()];

        foreach ([$first, $second, $third] as $player) {
            $this->actingAs($player)->post(route('open-play.join', $event))->assertSessionHasNoErrors();
        }
        $this->assertSame('waitlisted', $event->participants()->where('user_id', $third->id)->value('status'));

        $this->actingAs($first)->post(route('open-play.leave', $event))->assertRedirect();
        $this->assertSame('left', $event->participants()->where('user_id', $first->id)->value('status'));
        $this->assertSame('joined', $event->participants()->where('user_id', $third->id)->value('status'));
        $this->assertSame(2, $event->participants()->where('status', 'joined')->count());
    }

    public function test_eligibility_rules_block_ineligible_players_with_a_reason(): void
    {
        $event = OpenPlay::factory()->create(['gender_rule' => 'men', 'min_rating' => '3.00', 'max_rating' => '3.50']);
        $this->actingAs($this->completePlayer(['gender' => 'female']))->post(route('open-play.join', $event))->assertSessionHasErrors('join');
        $this->actingAs($this->completePlayer(['gender' => 'male', 'self_rating' => null]))->post(route('open-play.join', $event))->assertSessionHasErrors('join');
        $this->actingAs($this->completePlayer(['gender' => 'male', 'self_rating' => '4.00']))->post(route('open-play.join', $event))->assertSessionHasErrors('join');
        $this->actingAs($this->completePlayer(['gender' => 'male', 'self_rating' => '3.25']))->post(route('open-play.join', $event))->assertSessionHasNoErrors();
        $this->assertSame(1, $event->participants()->count());
    }

    public function test_hosts_cannot_join_their_own_event_or_join_past_or_cancelled_ones(): void
    {
        $event = OpenPlay::factory()->create();
        $this->actingAs($event->host)->post(route('open-play.join', $event))->assertSessionHasErrors('join');
        $past = OpenPlay::factory()->create(['starts_at' => now()->subHours(3), 'ends_at' => now()->subHour()]);
        $this->actingAs($this->completePlayer())->post(route('open-play.join', $past))->assertSessionHasErrors('join');
        $cancelled = OpenPlay::factory()->create(['status' => 'cancelled']);
        $this->actingAs($this->completePlayer())->post(route('open-play.join', $cancelled))->assertNotFound();
    }

    public function test_only_the_host_edits_and_capacity_cannot_drop_below_joined_players(): void
    {
        $event = OpenPlay::factory()->create(['capacity' => 4]);
        foreach (range(1, 3) as $ignored) {
            $event->participants()->create(['user_id' => $this->completePlayer()->id, 'status' => 'joined']);
        }

        $this->actingAs(User::factory()->create())->put(route('play.events.update', $event), $this->eventPayload())->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('play.events.edit', $event))->assertNotFound();
        $this->actingAs($event->host)->put(route('play.events.update', $event), $this->eventPayload(['capacity' => 2]))->assertSessionHasErrors('capacity');
        $this->actingAs($event->host)->put(route('play.events.update', $event), $this->eventPayload(['capacity' => 6, 'title' => 'Test Renamed Event']))->assertSessionHasNoErrors();
        $this->assertSame(['Test Renamed Event', 6], [$event->fresh()->title, $event->fresh()->capacity]);
    }

    public function test_event_validation_covers_location_ratings_and_past_publishing(): void
    {
        $host = $this->completePlayer();
        $draftVenue = Business::factory()->create();
        Court::factory()->create(['business_id' => $draftVenue->id]);

        $this->actingAs($host)->post(route('play.events.store'), $this->eventPayload(['location' => '']))->assertSessionHasErrors('location');
        $this->actingAs($host)->post(route('play.events.store'), $this->eventPayload(['business_id' => $draftVenue->id, 'location' => '']))->assertSessionHasErrors('business_id');
        $this->actingAs($host)->post(route('play.events.store'), $this->eventPayload(['min_rating' => '4.0', 'max_rating' => '3.0']))->assertSessionHasErrors('max_rating');
        $this->actingAs($host)->post(route('play.events.store'), $this->eventPayload(['date' => '2026-10-20']))->assertSessionHasErrors('date');
        $this->actingAs($host)->post(route('play.events.store'), $this->eventPayload(['date' => '2026-10-20', 'intent' => 'draft']))->assertSessionHasNoErrors();
        $this->assertSame('draft', OpenPlay::query()->sole()->status);
    }

    public function test_host_sees_contact_numbers_and_cancelling_hides_the_event_from_discovery(): void
    {
        $event = OpenPlay::factory()->create(['title' => 'Test Cancel Event']);
        $player = $this->completePlayer(['contact_number' => '09998887777']);
        $this->actingAs($player)->post(route('open-play.join', $event));

        $this->actingAs($event->host)->get(route('open-play.show', $event))->assertSee('09998887777');
        $this->actingAs($this->completePlayer())->get(route('open-play.show', $event))->assertDontSee('09998887777');

        $this->actingAs($event->host)->patch(route('play.events.cancel', $event))->assertRedirect(route('play.events'));
        $this->get(route('open-play.index'))->assertDontSee('Test Cancel Event');
        $this->actingAs($player)->get(route('play.events'))->assertSee('Cancelled by host');
    }
}

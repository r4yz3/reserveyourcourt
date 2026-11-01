<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Coach;
use App\Models\Court;
use App\Models\FeeSetting;
use App\Models\PlayerMatch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlayerAreaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function venue(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
        $business = Business::factory()->create(['name' => 'Test Player Club', 'status' => 'approved', 'address' => '12 Test Street', 'payment_instructions' => 'Test bank account. Do not send money.']);
        $court = Court::factory()->create(['business_id' => $business->id]);
        FeeSetting::factory()->create();

        return [$business, $court];
    }

    private function reservation(Court $court, array $overrides = []): array
    {
        return array_replace(['court_id' => $court->id, 'starts_at' => '2026-11-02T09:00', 'duration' => 60, 'customer_name' => 'Test Player', 'customer_contact' => '09171234567', 'accept_terms' => '1', 'request_key' => (string) Str::uuid()], $overrides);
    }

    private function scorePayload(array $overrides = []): array
    {
        return array_replace(['played_on' => '2026-10-30', 'format' => 'singles', 'location' => 'Test court', 'opponent_name' => 'Test Opponent', 'opponent_email' => '', 'games' => [['mine' => 11, 'theirs' => 7], ['mine' => 11, 'theirs' => 9]]], $overrides);
    }

    public function test_registration_creates_a_player_and_saves_guest_bookings_from_the_same_browser(): void
    {
        [$business, $court] = $this->venue();
        $this->post(route('public.reservations.store', $business), $this->reservation($court))->assertSessionHasNoErrors();
        $other = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'guest_key_hash' => hash('sha256', Str::random(64))]);

        $this->post(route('play.register.store'), ['name' => 'Test Player', 'email' => 'Test.Player@Example.test', 'password' => 'TestPassword123', 'password_confirmation' => 'TestPassword123'])
            ->assertRedirect(route('play.dashboard'));

        $player = User::query()->where('email', 'test.player@example.test')->sole();
        $this->assertAuthenticatedAs($player);
        $this->assertFalse($player->is_super_admin);
        $this->assertSame($player->id, Booking::query()->where('customer_name', 'Test Player')->sole()->player_id);
        $this->assertNull($other->fresh()->player_id);
    }

    public function test_signing_in_claims_only_this_browsers_guest_bookings(): void
    {
        [$business, $court] = $this->venue();
        $player = User::factory()->create(['password' => 'TestPassword123']);
        $this->post(route('public.reservations.store', $business), $this->reservation($court))->assertSessionHasNoErrors();

        $this->post(route('login.store'), ['email' => $player->email, 'password' => 'TestPassword123'])->assertRedirect(route('play.dashboard'));

        $this->assertSame($player->id, Booking::query()->sole()->player_id);
    }

    public function test_login_sends_each_role_to_its_own_home(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true, 'password' => 'TestPassword123']);
        $coach = Coach::factory()->create()->user;
        $coach->update(['password' => 'TestPassword123']);

        $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'TestPassword123'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));
        $this->post(route('login.store'), ['email' => $coach->email, 'password' => 'TestPassword123'])->assertRedirect(route('coach.portal'));
    }

    public function test_signed_in_checkout_links_the_booking_and_only_its_player_can_open_it_later(): void
    {
        [$business, $court] = $this->venue();
        $player = User::factory()->create();
        $this->actingAs($player)->post(route('public.reservations.store', $business), $this->reservation($court))->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->assertSame($player->id, $booking->player_id);

        $this->flushSession();
        $this->actingAs($player)->get(route('public.reservations.show', ['reference' => $booking->reference]))->assertOk()->assertSee('Saved to your account.');
        $this->actingAs(User::factory()->create())->get(route('public.reservations.show', ['reference' => $booking->reference]))->assertNotFound();
    }

    public function test_dashboard_and_bookings_show_only_the_players_own_reservations(): void
    {
        [$business, $court] = $this->venue();
        $player = User::factory()->create();
        Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'player_id' => $player->id, 'customer_name' => 'Mine']);
        $stranger = Booking::factory()->create(['player_id' => User::factory()->create()->id]);

        $this->actingAs($player)->get(route('play.dashboard'))->assertOk()->assertSee('Test Player Club')->assertDontSee($stranger->business->name);
        $this->actingAs($player)->get(route('play.bookings'))->assertOk()->assertSee('Test Player Club')->assertDontSee($stranger->business->name);
    }

    public function test_player_area_requires_sign_in(): void
    {
        $this->get(route('play.dashboard'))->assertRedirect(route('login'));
        $this->post(route('play.scores.store'), $this->scorePayload())->assertRedirect(route('login'));
    }

    public function test_profile_updates_only_allowed_fields(): void
    {
        $player = User::factory()->create();
        $this->actingAs($player)->put(route('play.profile.update'), ['name' => 'Test Renamed', 'skill_level' => '3.5', 'home_city' => 'Test City', 'is_super_admin' => true])->assertRedirect(route('play.profile'));
        $player->refresh();
        $this->assertSame(['Test Renamed', '3.5', 'Test City', false], [$player->name, $player->skill_level, $player->home_city, $player->is_super_admin]);
        $this->actingAs($player)->put(route('play.profile.update'), ['name' => 'Test Renamed', 'skill_level' => '9.9'])->assertSessionHasErrors('skill_level');
    }

    public function test_a_confirmed_result_counts_for_both_players(): void
    {
        $this->venue();
        $recorder = User::factory()->create();
        $opponent = User::factory()->create(['email' => 'opponent@example.test']);

        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['opponent_email' => 'Opponent@Example.test']))->assertSessionHasNoErrors()->assertRedirect(route('play.scores.index'));
        $match = PlayerMatch::query()->with('players')->sole();
        $this->assertSame(['unconfirmed', 1, [[11, 7], [11, 9]]], [$match->status, $match->winning_side, $match->games]);
        $this->assertSame($opponent->id, $match->players->firstWhere('side', 2)->user_id);
        $this->actingAs($recorder)->get(route('play.scores.index'))->assertSee('Awaiting confirmation')->assertSee('0–0');

        $this->actingAs($recorder)->patch(route('play.scores.review', $match), ['decision' => 'confirm'])->assertStatus(409);
        $this->actingAs($opponent)->get(route('play.dashboard'))->assertSee('Confirm these results');
        $this->actingAs($opponent)->patch(route('play.scores.review', $match), ['decision' => 'confirm'])->assertRedirect();

        $this->assertSame('confirmed', $match->fresh()->status);
        $this->actingAs($recorder)->get(route('play.scores.index'))->assertSee('1–0');
        $this->actingAs($opponent)->get(route('play.scores.index'))->assertSee('0–1');
        $this->actingAs($opponent)->patch(route('play.scores.review', $match), ['decision' => 'dispute'])->assertStatus(409);
    }

    public function test_disputed_and_unlinked_results_never_count(): void
    {
        $this->venue();
        $recorder = User::factory()->create();
        $opponent = User::factory()->create(['email' => 'opponent@example.test']);

        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['opponent_email' => 'opponent@example.test']));
        $this->actingAs($opponent)->patch(route('play.scores.review', PlayerMatch::query()->sole()), ['decision' => 'dispute'])->assertRedirect();
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['opponent_email' => 'nobody@example.test']))->assertSessionHasNoErrors();
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload())->assertSessionHasNoErrors();

        $this->assertSame(['disputed', 'unconfirmed', 'personal'], PlayerMatch::query()->orderBy('id')->pluck('status')->all());
        $this->actingAs($recorder)->get(route('play.scores.index'))->assertSee('0–0')->assertSee('Disputed');
    }

    public function test_opponent_lookup_gives_the_same_response_for_unknown_emails(): void
    {
        $this->venue();
        $recorder = User::factory()->create();
        User::factory()->create(['email' => 'known@example.test']);

        $known = $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['opponent_email' => 'known@example.test']));
        $unknown = $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['opponent_email' => 'unknown@example.test']));

        $this->assertSame($known->getSession()->get('success'), $unknown->getSession()->get('success'));
        $this->assertSame(['unconfirmed', 'unconfirmed'], PlayerMatch::query()->pluck('status')->all());
    }

    public function test_people_outside_a_match_cannot_review_or_remove_it(): void
    {
        $this->venue();
        $recorder = User::factory()->create();
        User::factory()->create(['email' => 'opponent@example.test']);
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['opponent_email' => 'opponent@example.test']));
        $match = PlayerMatch::query()->sole();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->patch(route('play.scores.review', $match), ['decision' => 'confirm'])->assertNotFound();
        $this->actingAs($stranger)->delete(route('play.scores.destroy', $match))->assertNotFound();
        $this->actingAs($stranger)->get(route('play.scores.index'))->assertDontSee('Test Opponent');
        $this->actingAs($recorder)->delete(route('play.scores.destroy', $match))->assertRedirect(route('play.scores.index'));
        $this->assertModelMissing($match);
    }

    public function test_doubles_records_partners_and_both_opponents(): void
    {
        $this->venue();
        $recorder = User::factory()->create();
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['format' => 'doubles']))->assertSessionHasErrors(['partner_name', 'second_opponent_name']);

        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['format' => 'doubles', 'partner_name' => 'Test Partner', 'second_opponent_name' => 'Test Second', 'games' => [['mine' => 8, 'theirs' => 11], ['mine' => 11, 'theirs' => 6], ['mine' => 9, 'theirs' => 11]]]))->assertSessionHasNoErrors();

        $match = PlayerMatch::query()->with('players')->sole();
        $this->assertSame(2, $match->winning_side);
        $this->assertSame([1, 1, 2, 2], $match->players->pluck('side')->all());
    }

    public function test_invalid_scores_are_rejected(): void
    {
        [$business, $court] = $this->venue();
        $recorder = User::factory()->create(['email' => 'recorder@example.test']);
        $foreignBooking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'player_id' => User::factory()->create()->id]);

        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['games' => [['mine' => 11, 'theirs' => 11]]]))->assertSessionHasErrors('games');
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['games' => [['mine' => 11, 'theirs' => 5], ['mine' => 5, 'theirs' => 11]]]))->assertSessionHasErrors('games');
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['opponent_email' => 'recorder@example.test']))->assertSessionHasErrors('opponent_email');
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['booking_id' => $foreignBooking->id]))->assertSessionHasErrors('booking_id');
        $this->actingAs($recorder)->post(route('play.scores.store'), $this->scorePayload(['played_on' => '2026-12-01']))->assertSessionHasErrors('played_on');
        $this->assertSame(0, PlayerMatch::query()->count());
    }
}

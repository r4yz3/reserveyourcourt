<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\FeeSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function venue(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
        $business = Business::factory()->create(['name' => 'Test Public Club', 'status' => 'approved', 'address' => '12 Test Street', 'payment_instructions' => 'Test bank account. Do not send money.']);
        $court = Court::factory()->create(['business_id' => $business->id]);
        FeeSetting::factory()->create();

        return [$business, $court];
    }

    private function payload(Court $court, array $overrides = []): array
    {
        return array_replace(['court_id' => $court->id, 'starts_at' => '2026-11-02T09:00', 'duration' => 60, 'customer_name' => 'Test Guest', 'customer_contact' => '09171234567', 'accept_terms' => '1', 'request_key' => (string) Str::uuid()], $overrides);
    }

    public function test_home_page_links_to_every_way_to_play_and_counts_courts_correctly(): void
    {
        [$business] = $this->venue();

        $this->get(route('home'))->assertOk()
            ->assertSee($business->city.' · 1 court')
            ->assertDontSee('1 courts')
            ->assertSee('How booking works')
            ->assertSee('href="'.route('open-play.index').'"', false)
            ->assertSee('href="'.route('public.coaches.index').'"', false)
            ->assertSee('href="'.route('clubs.index').'"', false);
    }

    public function test_mobile_tab_bar_marks_the_current_section_and_follows_sign_in_state(): void
    {
        $this->get(route('clubs.index'))->assertOk()
            ->assertSee('class="mobile-tab-bar"', false)
            ->assertSee('<a href="'.route('clubs.index').'"  aria-current="page" >', false)
            ->assertSee('<a href="'.route('login').'" ><span class="mobile-tab-icon">', false);

        $this->actingAs(User::factory()->create())->get(route('home'))->assertOk()
            ->assertSee('<a href="'.route('public.venues.index').'"  aria-current="page" >', false)
            ->assertSee('<a href="'.route('play.dashboard').'" ><span class="mobile-tab-icon">', false);
    }

    public function test_discovery_only_shows_approved_real_venues_with_active_courts(): void
    {
        [$visible] = $this->venue();
        $hidden = Business::factory()->create(['name' => 'Hidden Draft']);
        Court::factory()->create(['business_id' => $hidden->id]);
        $demo = Business::factory()->create(['name' => 'Hidden Demo', 'status' => 'approved', 'is_demo' => true]);
        Court::factory()->create(['business_id' => $demo->id]);
        $this->get(route('public.venues.index'))->assertOk()->assertSee($visible->name)->assertDontSee('Hidden Draft')->assertDontSee('Hidden Demo');
        $this->get(route('public.venues.show', $hidden))->assertNotFound();
        $this->get(route('public.venues.show', $demo))->assertNotFound();
    }

    public function test_city_search_filters_real_venue_results(): void
    {
        [$visible] = $this->venue();
        $this->get(route('public.venues.index', ['search' => $visible->city]))->assertOk()->assertSee($visible->name);
        $this->get(route('public.venues.index', ['search' => 'Unmatched-city-xxxxx']))->assertOk()->assertDontSee($visible->name)->assertSee('No courts match this search.');
    }

    public function test_guest_checkout_creates_only_an_unpaid_hold_without_a_user_account(): void
    {
        [$business,$court] = $this->venue();
        $users = User::query()->count();
        $this->post(route('public.reservations.store', $business), $this->payload($court))->assertSessionHasNoErrors()->assertRedirect();
        $booking = Booking::query()->sole();
        $this->assertSame('held', $booking->status);
        $this->assertSame('instapay', $booking->payment_method);
        $this->assertSame(0, $booking->paid_centavos);
        $this->assertNull($booking->created_by);
        $this->assertSame($users, User::query()->count());
        $this->assertNotNull($booking->guest_key_hash);
        $this->assertArrayNotHasKey('guest_key_hash', $booking->toArray());
        $this->get(route('public.reservations.show', ['reference' => $booking->reference]))->assertOk()->assertSee('One more step. Pay in full.')->assertSee('Test Guest');
    }

    public function test_another_guest_cannot_read_or_upload_to_a_reservation(): void
    {
        [$business,$court] = $this->venue();
        $this->post(route('public.reservations.store', $business), $this->payload($court))->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->withSession(['player_key' => Str::random(64)])->get(route('public.reservations.show', ['reference' => $booking->reference]))->assertNotFound();
        $this->post(route('public.reservations.proof', ['reference' => $booking->reference]), ['reference' => 'BANK-1'])->assertNotFound();
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_guest_cannot_supply_paid_state_cash_method_or_walk_in_source(): void
    {
        [$business,$court] = $this->venue();
        $this->post(route('public.reservations.store', $business), $this->payload($court, ['status' => 'confirmed', 'paid_centavos' => 40000, 'payment_method' => 'cash', 'source' => 'walk_in']))->assertSessionHasErrors(['status', 'paid_centavos', 'payment_method', 'source']);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('inventory_claims', 0);
    }

    public function test_guest_checkout_cannot_use_a_court_from_another_business(): void
    {
        [$business] = $this->venue();
        $other = Court::factory()->create();
        $this->post(route('public.reservations.store', $business), $this->payload($other))->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_guest_checkout_is_idempotent_and_conflicts_use_the_shared_inventory(): void
    {
        [$business,$court] = $this->venue();
        $payload = $this->payload($court);
        $this->post(route('public.reservations.store', $business), $payload)->assertSessionHasNoErrors();
        $this->post(route('public.reservations.store', $business), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);
        $this->post(route('public.reservations.store', $business), $this->payload($court))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('inventory_claims', 2);
    }

    public function test_availability_does_not_offer_occupied_times_or_leak_player_names(): void
    {
        [$business,$court] = $this->venue();
        $this->post(route('public.reservations.store', $business), $this->payload($court))->assertSessionHasNoErrors();
        $response = $this->get(route('public.venues.show', ['business' => $business, 'date' => '2026-11-02', 'duration' => 60]));
        $response->assertOk()->assertDontSee('value="'.$court->id.'|2026-11-02T09:00"', false)->assertDontSee('Test Guest')->assertSee('value="'.$court->id.'|2026-11-02T10:00"', false);
    }

    public function test_native_radio_selection_is_parsed_on_the_server_without_javascript(): void
    {
        [$business,$court] = $this->venue();
        $payload = $this->payload($court);
        unset($payload['court_id'],$payload['starts_at']);
        $payload['selection'] = $court->id.'|2026-11-02T11:30';
        $this->post(route('public.reservations.store', $business), $payload)->assertSessionHasNoErrors();
        $this->assertSame('03:30', Booking::query()->sole()->starts_at->format('H:i'));
    }

    public function test_guest_receipt_submission_waits_for_venue_full_payment_verification(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP required.');
        }
        [$business,$court] = $this->venue();
        Storage::fake('local');
        $this->post(route('public.reservations.store', $business), $this->payload($court))->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->post(route('public.reservations.proof', ['reference' => $booking->reference]), ['reference' => 'BANK-123', 'receipt' => UploadedFile::fake()->image('receipt.png', 200, 100)])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('awaiting_review', $booking->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paid_centavos);
        $owner = User::factory()->create();
        $business->users()->attach($owner, ['role' => 'owner']);
        $proof = $booking->proofs()->sole();
        $this->actingAs($owner)->patch(route('proofs.update', $proof), ['decision' => 'verify', 'received_amount' => '400'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(40000, $booking->fresh()->paid_centavos);
        $this->get(route('public.reservations.show', ['reference' => $booking->reference]))->assertOk()->assertSee('Your game is confirmed.');
    }

    public function test_expired_hold_shows_no_payment_upload_and_cannot_accept_a_receipt(): void
    {
        [$business,$court] = $this->venue();
        $this->post(route('public.reservations.store', $business), $this->payload($court))->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->travel(16)->minutes();
        $this->get(route('public.reservations.show', ['reference' => $booking->reference]))->assertOk()->assertSee('This reservation is not active.')->assertDontSee('name="receipt"', false);
        $this->post(route('public.reservations.proof', ['reference' => $booking->reference]), ['reference' => 'late', 'receipt' => UploadedFile::fake()->image('receipt.png', 100, 50)])->assertConflict();
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_new_guests_cannot_reuse_another_guests_idempotency_key(): void
    {
        [$business,$court] = $this->venue();
        $payload = $this->payload($court);
        $this->post(route('public.reservations.store', $business), $payload)->assertSessionHasNoErrors();
        $this->withSession(['player_key' => Str::random(64)])->post(route('public.reservations.store', $business), $payload)->assertConflict();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_unapproved_or_suspended_businesses_reject_new_guest_checkout(): void
    {
        [$business,$court] = $this->venue();
        $business->update(['status' => 'suspended']);
        $this->post(route('public.reservations.store', $business), $this->payload($court))->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_public_photo_is_available_only_after_approval(): void
    {
        [$business] = $this->venue();
        Storage::fake('local');
        Storage::disk('local')->put('venue-photos/test.webp', 'test');
        $business->update(['photo_disk' => 'local', 'photo_path' => 'venue-photos/test.webp']);
        $this->get(route('public.venues.photo', $business))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $business->update(['status' => 'draft']);
        $this->get(route('public.venues.photo', $business))->assertNotFound();
    }

    public function test_guest_requires_full_payment_acknowledgement(): void
    {
        [$business,$court] = $this->venue();
        $this->post(route('public.reservations.store', $business), $this->payload($court, ['accept_terms' => '0']))->assertSessionHasErrors('accept_terms');
        $this->assertDatabaseCount('bookings', 0);
    }
}

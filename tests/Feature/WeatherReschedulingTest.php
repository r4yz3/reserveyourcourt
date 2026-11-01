<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\CourtClosure;
use App\Models\FeeSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WeatherReschedulingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupVenue(string $role = 'owner'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'UTC'));
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => 'approved']);
        $business->users()->attach($user, ['role' => $role]);
        $court = Court::factory()->create(['business_id' => $business->id, 'type' => 'Outdoor']);
        FeeSetting::factory()->create();
        $this->actingAs($user);
        $booking = $this->reserve($business, $court);

        return [$user, $business, $court, $booking];
    }

    private function reserve(Business $business, Court $court, string $start = '2026-11-02T09:00'): Booking
    {
        $this->post(route('bookings.store', $business), ['court_id' => $court->id, 'starts_at' => $start, 'duration' => 60, 'customer_name' => 'Player', 'customer_contact' => '09171234567', 'request_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'paid_amount' => '400'])->assertSessionHasNoErrors();

        return Booking::query()->latest('id')->firstOrFail();
    }

    private function data(Court $court, array $overrides = []): array
    {
        return array_replace(['court_id' => $court->id, 'starts_at' => '2026-11-03T10:00', 'reason' => 'rain', 'staff_notes' => 'Private safety inspection', 'expected_version' => 0, 'request_key' => (string) Str::uuid()], $overrides);
    }

    public function test_paid_booking_moves_without_changing_payment_fee_reference_or_duration(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $original = $booking->getAttributes();
        $this->get(route('bookings.reschedule.edit', $booking))->assertOk()->assertSee('Replacement schedule');
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court))->assertRedirect(route('bookings.show', $booking))->assertSessionHasNoErrors();
        $booking->refresh();
        foreach (['reference', 'total_centavos', 'paid_centavos', 'commission_centavos', 'fee_setting_id', 'status'] as $field) {
            $this->assertEquals($original[$field], $booking->$field);
        }
        $this->assertSame('2026-11-03 02:00', $booking->starts_at->format('Y-m-d H:i'));
        $this->assertEquals(60, $booking->starts_at->diffInMinutes($booking->ends_at));
        $this->assertDatabaseCount('inventory_claims', 2);
        $this->assertDatabaseMissing('inventory_claims', ['court_id' => $court->id, 'starts_at' => $original['starts_at']]);
        $this->assertDatabaseCount('booking_reschedules', 1);
        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('Private safety inspection')->assertSee('Schedule changes');
    }

    public function test_move_to_more_expensive_indoor_court_keeps_original_price(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $indoor = Court::factory()->create(['business_id' => $business->id, 'hourly_rate_centavos' => 90000]);
        $this->post(route('bookings.reschedule.store', $booking), $this->data($indoor))->assertSessionHasNoErrors();
        $this->assertSame($indoor->id, $booking->fresh()->court_id);
        $this->assertSame(40000, $booking->fresh()->total_centavos);
    }

    public function test_conflict_preserves_original_booking_and_claims(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $this->reserve($business, $court, '2026-11-03T10:00');
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court))->assertSessionHasErrors('starts_at');
        $this->assertSame(0, $booking->fresh()->reschedule_version);
        $this->assertDatabaseCount('inventory_claims', 4);
        $this->assertDatabaseCount('booking_reschedules', 0);
    }

    public function test_closed_replacement_is_rejected(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        CourtClosure::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'starts_at' => CarbonImmutable::parse('2026-11-03 02:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-03 03:00', 'UTC')]);
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('booking_reschedules', 0);
    }

    public function test_self_overlapping_move_replaces_inventory_atomically(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court, ['starts_at' => '2026-11-02T09:30']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('inventory_claims', 2);
        $this->assertSame('01:30', $booking->fresh()->starts_at->format('H:i'));
    }

    public function test_retry_is_idempotent_and_stale_form_cannot_move_again(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $data = $this->data($court);
        $url = route('bookings.reschedule.store', $booking);
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $data)->assertSessionHasNoErrors();
        $this->post($url, $this->data($court))->assertSessionHasErrors('booking');
        $this->assertDatabaseCount('booking_reschedules', 1);
    }

    public function test_only_fully_paid_confirmed_bookings_are_eligible(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        foreach ([['status' => 'confirmed', 'paid_centavos' => 0], ['status' => 'held', 'paid_centavos' => 0], ['status' => 'cancelled', 'paid_centavos' => 40000], ['status' => 'completed', 'paid_centavos' => 40000]] as $state) {
            $booking->update($state);
            $this->post(route('bookings.reschedule.store', $booking), $this->data($court))->assertSessionHasErrors('booking');
        }$this->assertDatabaseCount('booking_reschedules', 0);
    }

    public function test_owner_can_close_original_slot_with_move(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court, ['block_original' => 1, 'closure_ends_at' => '2026-11-02T12:00']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('court_closures', 1);
        $this->assertNotNull($booking->reschedules()->first()->court_closure_id);
    }

    public function test_closure_conflict_rolls_back_entire_move(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $this->reserve($business, $court, '2026-11-02T10:00');
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court, ['block_original' => 1, 'closure_ends_at' => '2026-11-02T12:00']))->assertSessionHasErrors('starts_at');
        $this->assertSame(0, $booking->fresh()->reschedule_version);
        $this->assertDatabaseCount('inventory_claims', 4);
        $this->assertDatabaseCount('court_closures', 0);
        $this->assertDatabaseCount('booking_reschedules', 0);
    }

    public function test_staff_can_move_but_cannot_close_court_and_other_tenants_cannot_access(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue('staff');
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court, ['block_original' => 1, 'closure_ends_at' => '2026-11-02T12:00']))->assertForbidden();
        $other = Court::factory()->create();
        $this->post(route('bookings.reschedule.store', $booking), $this->data($other))->assertNotFound();
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court))->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create())->get(route('bookings.reschedule.edit', $booking))->assertNotFound();
    }

    public function test_guest_sees_changed_schedule_without_private_notes(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        $key = Str::random(64);
        $booking->update(['guest_key_hash' => hash('sha256', $key)]);
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court))->assertSessionHasNoErrors();
        $this->withSession(['player_key' => $key])->get(route('public.reservations.show', $booking->reference))->assertOk()->assertSee('Rain / unsafe weather')->assertDontSee('Private safety inspection');
        $this->withSession(['player_key' => 'other'])->get(route('public.reservations.show', $booking->reference))->assertNotFound();
    }

    public function test_past_out_of_hours_and_inactive_replacements_are_rejected(): void
    {
        [$user,$business,$court,$booking] = $this->setupVenue();
        foreach (['2026-10-30T09:00', '2026-11-03T05:00', '2026-11-03T09:15'] as $start) {
            $this->post(route('bookings.reschedule.store', $booking), $this->data($court, ['starts_at' => $start]))->assertSessionHasErrors('starts_at');
        }$court->update(['is_active' => false]);
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('booking_reschedules', 0);
    }

    public function test_unplayable_past_booking_can_be_moved_and_receipt_is_preserved(): void
    {
        [$user, $business, $court, $booking] = $this->setupVenue();
        $booking->update(['payment_method' => 'instapay']);
        $proof = $booking->proofs()->create(['path' => 'private/original.webp', 'reference' => 'Original transfer', 'status' => 'verified']);
        $this->travel(3)->days();
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court, ['reason' => 'unplayable', 'starts_at' => '2026-11-05T10:00']))->assertSessionHasNoErrors();
        $this->assertSame('unplayable', $booking->reschedules()->sole()->reason);
        $this->assertSame('private/original.webp', $proof->fresh()->path);
        $this->assertSame('verified', $proof->fresh()->status);
        $this->assertSame(40000, $booking->fresh()->paid_centavos);
    }

    public function test_reschedule_cannot_override_payment_or_duration(): void
    {
        [$user, $business, $court, $booking] = $this->setupVenue();
        $this->post(route('bookings.reschedule.store', $booking), $this->data($court, ['paid_centavos' => 1, 'commission_centavos' => 0, 'duration' => 120]))->assertSessionHasErrors(['paid_centavos', 'commission_centavos', 'duration']);
        $this->assertSame(0, $booking->fresh()->reschedule_version);
    }

    public function test_replacement_picker_excludes_taken_slots_but_allows_overlap_with_itself(): void
    {
        [$user, $business, $court, $booking] = $this->setupVenue();
        $this->reserve($business, $court, '2026-11-02T11:00');
        $response = $this->get(route('bookings.reschedule.edit', ['booking' => $booking, 'court' => $court->id, 'date' => '2026-11-02']));
        $response->assertOk()->assertSee('value="2026-11-02T09:30"', false)->assertDontSee('value="2026-11-02T09:00"', false)->assertDontSee('value="2026-11-02T10:30"', false)->assertDontSee('value="2026-11-02T11:00"', false)->assertSee('value="2026-11-02T12:00"', false);
        $this->assertDatabaseCount('booking_reschedules', 0);
        $this->assertDatabaseCount('inventory_claims', 4);
    }

    public function test_replacement_picker_hides_closed_slots_and_keeps_duration(): void
    {
        [$user, $business, $court, $booking] = $this->setupVenue();
        $indoor = Court::factory()->create(['business_id' => $business->id, 'opens_at' => 9, 'closes_at' => 12]);
        CourtClosure::factory()->create(['business_id' => $business->id, 'court_id' => $indoor->id, 'starts_at' => CarbonImmutable::parse('2026-11-03 02:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-03 03:00', 'UTC')]);
        $response = $this->get(route('bookings.reschedule.edit', ['booking' => $booking, 'court' => $indoor->id, 'date' => '2026-11-03']));
        $response->assertOk()->assertSee('value="2026-11-03T09:00"', false)->assertDontSee('value="2026-11-03T09:30"', false)->assertDontSee('value="2026-11-03T10:00"', false)->assertSee('value="2026-11-03T11:00"', false)->assertDontSee('value="2026-11-03T11:30"', false)->assertSee('60 minutes per reservation');
    }

    public function test_replacement_picker_ignores_expired_holds_but_hides_active_holds(): void
    {
        [$user, $business, $court, $booking] = $this->setupVenue();
        $hold = $this->reserve($business, $court, '2026-11-03T10:00');
        $hold->update(['status' => 'held', 'paid_centavos' => 0, 'hold_expires_at' => now()->addMinutes(15)]);
        $url = route('bookings.reschedule.edit', ['booking' => $booking, 'court' => $court->id, 'date' => '2026-11-03']);
        $this->get($url)->assertOk()->assertDontSee('value="2026-11-03T10:00"', false);
        $this->travel(16)->minutes();
        $this->get($url)->assertOk()->assertSee('value="2026-11-03T10:00"', false);
    }

    public function test_picker_filters_are_tenant_scoped_and_dates_are_bounded(): void
    {
        [$user, $business, $court, $booking] = $this->setupVenue();
        $other = Court::factory()->create();
        $this->get(route('bookings.reschedule.edit', ['booking' => $booking, 'court' => $other->id]))->assertNotFound();
        $court->update(['is_active' => false]);
        $this->get(route('bookings.reschedule.edit', ['booking' => $booking, 'court' => $court->id]))->assertNotFound();
        foreach (['2026-10-31', '2027-03-01', 'invalid'] as $date) {
            $this->get(route('bookings.reschedule.edit', ['booking' => $booking, 'date' => $date]))->assertSessionHasErrors('date');
        }
    }

    public function test_picker_shows_empty_state_when_duration_does_not_fit(): void
    {
        [$user, $business, $court, $booking] = $this->setupVenue();
        $court->update(['opens_at' => 9, 'closes_at' => 10]);
        CourtClosure::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'starts_at' => CarbonImmutable::parse('2026-11-03 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-03 02:00', 'UTC')]);
        $this->get(route('bookings.reschedule.edit', ['booking' => $booking, 'court' => $court->id, 'date' => '2026-11-03']))->assertOk()->assertSee('No replacement times fit this booking')->assertDontSee('name="starts_at"', false);
    }
}

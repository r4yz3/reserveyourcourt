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

class CourtManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function venue(string $role = 'owner'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
        $user = User::factory()->create();
        $business = Business::factory()->create(['status' => 'approved', 'address' => 'Test Street', 'payment_instructions' => 'Test bank details.']);
        $business->users()->attach($user, ['role' => $role]);
        $court = Court::factory()->create(['business_id' => $business->id]);
        FeeSetting::factory()->create();

        return [$user, $business, $court];
    }

    private function courtData(Court $court, array $overrides = []): array
    {
        return array_replace(['name' => $court->name, 'type' => $court->type, 'hourly_rate' => '500.50', 'opens_at' => 6, 'closes_at' => 22, 'is_active' => '1'], $overrides);
    }

    private function closureData(array $overrides = []): array
    {
        return array_replace(['starts_at' => '2026-11-02T09:00', 'ends_at' => '2026-11-02T11:00', 'reason' => 'Surface maintenance'], $overrides);
    }

    private function reservation(Court $court, array $overrides = []): array
    {
        return array_replace(['court_id' => $court->id, 'starts_at' => '2026-11-02T09:00', 'duration' => 60, 'customer_name' => 'Test Guest', 'customer_contact' => '09171234567', 'request_key' => (string) Str::uuid(), 'payment_method' => 'cash', 'paid_amount' => '400'], $overrides);
    }

    public function test_owner_can_edit_court_and_price_changes_preserve_booking_snapshots(): void
    {
        [$owner,$business,$court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'total_centavos' => 40000, 'paid_centavos' => 40000, 'commission_centavos' => 2500]);
        $this->actingAs($owner)->get(route('courts.edit', [$business, $court]))->assertOk()->assertSee('Block court time');
        $this->put(route('courts.update', [$business, $court]), $this->courtData($court))->assertSessionHasNoErrors();
        $this->assertSame(50050, $court->fresh()->hourly_rate_centavos);
        $this->assertSame(40000, $booking->fresh()->total_centavos);
        $this->assertSame(2500, $booking->fresh()->commission_centavos);
        $this->assertDatabaseHas('audit_events', ['action' => 'court.updated']);
    }

    public function test_court_edit_and_closures_are_owner_only_and_tenant_scoped(): void
    {
        [$staff,$business,$court] = $this->venue('staff');
        $this->actingAs($staff)->get(route('courts.edit', [$business, $court]))->assertForbidden();
        $this->put(route('courts.update', [$business, $court]), $this->courtData($court))->assertForbidden();
        $this->post(route('closures.store', [$business, $court]), $this->closureData())->assertForbidden();
        $other = Business::factory()->create();
        $otherCourt = Court::factory()->create(['business_id' => $other->id]);
        $this->get(route('courts.edit', [$business, $otherCourt]))->assertNotFound();
        $this->post(route('closures.store', [$other, $otherCourt]), $this->closureData())->assertNotFound();
    }

    public function test_court_with_upcoming_paid_booking_cannot_be_deactivated_or_have_conflicting_hours(): void
    {
        [$owner,$business,$court] = $this->venue();
        Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'starts_at' => CarbonImmutable::parse('2026-11-02 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-02 02:00', 'UTC'), 'paid_centavos' => 40000]);
        $this->actingAs($owner)->put(route('courts.update', [$business, $court]), $this->courtData($court, ['is_active' => '0']))->assertSessionHasErrors('is_active');
        $this->put(route('courts.update', [$business, $court]), $this->courtData($court, ['opens_at' => 10]))->assertSessionHasErrors('opens_at');
        $this->assertTrue($court->fresh()->is_active);
        $this->assertSame(6, $court->fresh()->opens_at);
    }

    public function test_deactivating_last_active_court_returns_business_to_draft(): void
    {
        [$owner,$business,$court] = $this->venue();
        $this->actingAs($owner)->put(route('courts.update', [$business, $court]), $this->courtData($court, ['is_active' => '0']))->assertSessionHasNoErrors();
        $this->assertFalse($court->fresh()->is_active);
        $this->assertSame('draft', $business->fresh()->status);
    }

    public function test_closure_blocks_staff_and_guest_booking_and_is_removed_from_public_availability(): void
    {
        [$owner,$business,$court] = $this->venue();
        $this->actingAs($owner)->post(route('closures.store', [$business, $court]), $this->closureData())->assertSessionHasNoErrors();
        $closure = CourtClosure::query()->sole();
        $this->assertSame('01:00', $closure->starts_at->format('H:i'));
        $this->post(route('bookings.store', $business), $this->reservation($court))->assertSessionHasErrors('starts_at');
        $guest = $this->reservation($court, ['accept_terms' => '1']);
        unset($guest['paid_amount'],$guest['payment_method']);
        $this->post(route('public.reservations.store', $business), $guest)->assertSessionHasErrors('starts_at');
        $this->get(route('public.venues.show', ['business' => $business, 'date' => '2026-11-02']))->assertOk()->assertDontSee('value="'.$court->id.'|2026-11-02T09:00"', false)->assertDontSee('Surface maintenance')->assertSee('value="'.$court->id.'|2026-11-02T11:00"', false);
        $this->get(route('dashboard', ['business' => $business->id, 'date' => '2026-11-02']))->assertOk()->assertSee('Surface maintenance')->assertSee('maintenance-slot');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_closure_cannot_cancel_an_existing_paid_reservation(): void
    {
        [$owner,$business,$court] = $this->venue();
        $this->actingAs($owner)->post(route('bookings.store', $business), $this->reservation($court))->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->post(route('closures.store', [$business, $court]), $this->closureData())->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('court_closures', 0);
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(40000, $booking->fresh()->paid_centavos);
    }

    public function test_active_payment_holds_prevent_closure_but_expired_holds_do_not(): void
    {
        [$owner,$business,$court] = $this->venue();
        $this->actingAs($owner)->post(route('bookings.store', $business), $this->reservation($court, ['payment_method' => 'instapay', 'paid_amount' => '0']))->assertSessionHasNoErrors();
        $this->post(route('closures.store', [$business, $court]), $this->closureData())->assertSessionHasErrors('starts_at');
        $this->put(route('courts.update', [$business, $court]), $this->courtData($court, ['is_active' => '0']))->assertSessionHasErrors('is_active');
        $this->travel(16)->minutes();
        $this->post(route('closures.store', [$business, $court]), $this->closureData())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('court_closures', 1);
    }

    public function test_removing_a_closure_restores_availability_and_preserves_history(): void
    {
        [$owner,$business,$court] = $this->venue();
        $closure = CourtClosure::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'starts_at' => CarbonImmutable::parse('2026-11-02 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-02 03:00', 'UTC')]);
        $this->actingAs($owner)->delete(route('closures.destroy', [$business, $court, $closure]))->assertRedirect();
        $this->assertNotNull($closure->fresh()->cancelled_at);
        $this->assertSame($owner->id, $closure->fresh()->cancelled_by);
        $this->post(route('bookings.store', $business), $this->reservation($court))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('court_closures', 1);
    }

    public function test_closure_removal_cannot_target_another_court_or_business(): void
    {
        [$owner,$business,$court] = $this->venue();
        $otherCourt = Court::factory()->create(['business_id' => $business->id]);
        $closure = CourtClosure::factory()->create(['business_id' => $business->id, 'court_id' => $otherCourt->id]);
        $this->actingAs($owner)->delete(route('closures.destroy', [$business, $court, $closure]))->assertNotFound();
        $this->assertNull($closure->fresh()->cancelled_at);
    }

    public function test_overlapping_closures_are_rejected_but_adjacent_closures_are_allowed(): void
    {
        [$owner,$business,$court] = $this->venue();
        $this->actingAs($owner)->post(route('closures.store', [$business, $court]), $this->closureData())->assertSessionHasNoErrors();
        $this->post(route('closures.store', [$business, $court]), $this->closureData(['starts_at' => '2026-11-02T10:30', 'ends_at' => '2026-11-02T12:00']))->assertSessionHasErrors('starts_at');
        $this->post(route('closures.store', [$business, $court]), $this->closureData(['starts_at' => '2026-11-02T11:00', 'ends_at' => '2026-11-02T12:00']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('court_closures', 2);
    }

    public function test_invalid_half_hour_boundary_or_excessive_range_is_rejected(): void
    {
        [$owner,$business,$court] = $this->venue();
        $this->actingAs($owner)->post(route('closures.store', [$business, $court]), $this->closureData(['starts_at' => '2026-11-02T09:15']))->assertSessionHasErrors('starts_at');
        $this->post(route('closures.store', [$business, $court]), $this->closureData(['ends_at' => '2026-11-10T10:00']))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('court_closures', 0);
    }

    public function test_multi_day_closure_blocks_next_day_availability(): void
    {
        [$owner,$business,$court] = $this->venue();
        $this->actingAs($owner)->post(route('closures.store', [$business, $court]), $this->closureData(['starts_at' => '2026-11-02T20:00', 'ends_at' => '2026-11-03T10:00']))->assertSessionHasNoErrors();
        $this->get(route('public.venues.show', ['business' => $business, 'date' => '2026-11-03']))->assertOk()->assertDontSee('value="'.$court->id.'|2026-11-03T09:00"', false)->assertSee('value="'.$court->id.'|2026-11-03T10:00"', false);
    }
}

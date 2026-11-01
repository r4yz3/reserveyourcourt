<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CourtDisruptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function venue(string $role = 'owner'): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'UTC'));
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user, ['role' => $role]);
        $court = Court::factory()->create(['business_id' => $business->id, 'type' => 'Outdoor']);
        $this->actingAs($user);

        return [$user, $business, $court];
    }

    private function booking(Business $business, Court $court, array $overrides = []): Booking
    {
        return Booking::factory()->create(array_replace(['business_id' => $business->id, 'court_id' => $court->id, 'customer_name' => 'Rain Player', 'status' => 'confirmed', 'total_centavos' => 40000, 'paid_centavos' => 40000, 'starts_at' => CarbonImmutable::parse('2026-11-02 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-02 02:00', 'UTC')], $overrides));
    }

    private function url(Business $business, Court $court, array $overrides = []): string
    {
        return route('disruptions.index', array_replace(['business' => $business, 'court' => $court->id, 'from' => '2026-11-02T09:30', 'until' => '2026-11-02T11:00'], $overrides));
    }

    public function test_review_lists_overlapping_paid_reservations_and_is_read_only(): void
    {
        [$user,$business,$court] = $this->venue();
        $booking = $this->booking($business, $court);
        $this->get($this->url($business, $court))->assertOk()->assertSee('Rain Player')->assertSee('1 to review · 1 ready to move')->assertSee(route('bookings.reschedule.edit', $booking), false)->assertSee('Manage '.$court->name);
        $this->assertSame(0, $booking->fresh()->reschedule_version);
        $this->assertDatabaseCount('court_closures', 0);
        $this->assertDatabaseCount('booking_reschedules', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_review_includes_active_holds_and_unpaid_history_without_move_action(): void
    {
        [$user,$business,$court] = $this->venue();
        $this->booking($business, $court, ['customer_name' => 'Pending Player', 'status' => 'held', 'paid_centavos' => 0, 'hold_expires_at' => now()->addMinutes(15)]);
        $this->booking($business, $court, ['customer_name' => 'Unpaid Player', 'paid_centavos' => 0]);
        $this->get($this->url($business, $court))->assertOk()->assertSee('Pending Player')->assertSee('Unpaid Player')->assertSee('2 to review · 0 ready to move')->assertDontSee('>Move reservation<', false)->assertSee('Review booking');
    }

    public function test_review_excludes_expired_cancelled_completed_and_adjacent_bookings(): void
    {
        [$user,$business,$court] = $this->venue();
        foreach (['cancelled', 'completed', 'expired', 'no_show'] as $status) {
            $this->booking($business, $court, ['status' => $status]);
        }$this->booking($business, $court, ['status' => 'awaiting_review', 'hold_expires_at' => now()->subMinute()]);
        $this->booking($business, $court, ['ends_at' => CarbonImmutable::parse('2026-11-02 01:30', 'UTC')]);
        $this->booking($business, $court, ['starts_at' => CarbonImmutable::parse('2026-11-02 03:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-02 04:00', 'UTC')]);
        $this->get($this->url($business, $court))->assertOk()->assertSee('No affected reservations.')->assertDontSee('Rain Player');
    }

    public function test_staff_access_is_scoped_and_does_not_offer_owner_closure_controls(): void
    {
        [$user,$business,$court] = $this->venue('staff');
        $this->booking($business, $court);
        $this->get($this->url($business, $court))->assertOk()->assertDontSee('Stop new bookings');
        $otherCourt = Court::factory()->create();
        $this->get($this->url($business, $otherCourt))->assertNotFound();
        $this->get(route('disruptions.index', $otherCourt->business_id))->assertNotFound();
        $this->actingAs(User::factory()->create())->get($this->url($business, $court))->assertNotFound();
    }

    public function test_review_validates_window_and_accepts_retrospective_rain_review(): void
    {
        [$user,$business,$court] = $this->venue();
        foreach ([['until' => '2026-11-02T08:00'], ['until' => '2026-11-10T10:00'], ['until' => 'invalid']] as $overrides) {
            $this->get($this->url($business, $court, $overrides))->assertSessionHasErrors('until');
        }$this->get($this->url($business, $court, ['from' => '2026-10-31T09:00', 'until' => '2026-10-31T12:00']))->assertOk();
    }

    public function test_outdoor_court_is_selected_by_default_and_empty_venue_is_supported(): void
    {
        [$user,$business,$court] = $this->venue();
        Court::factory()->create(['business_id' => $business->id, 'name' => 'AAA indoor', 'type' => 'Indoor']);
        $this->booking($business, $court);
        $this->get(route('disruptions.index', ['business' => $business, 'date' => '2026-11-02']))->assertOk()->assertSee('Rain Player');
        $empty = Business::factory()->create();
        $empty->users()->attach($user, ['role' => 'owner']);
        $this->get(route('disruptions.index', $empty))->assertOk()->assertSee('No courts to review.');
    }

    public function test_moved_booking_leaves_affected_window_and_keeps_payment(): void
    {
        [$user,$business,$court] = $this->venue();
        $booking = $this->booking($business, $court);
        $this->post(route('bookings.reschedule.store', $booking), ['court_id' => $court->id, 'starts_at' => '2026-11-03T10:00', 'reason' => 'rain', 'expected_version' => 0, 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->get($this->url($business, $court))->assertOk()->assertSee('No affected reservations.')->assertDontSee('Rain Player');
        $this->assertSame(40000, $booking->fresh()->paid_centavos);
    }

    public function test_review_pagination_keeps_window_and_global_ready_count(): void
    {
        [$user,$business,$court] = $this->venue();
        Booking::factory()->count(26)->create(['business_id' => $business->id, 'court_id' => $court->id, 'starts_at' => CarbonImmutable::parse('2026-11-02 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-02 02:00', 'UTC'), 'status' => 'confirmed', 'total_centavos' => 40000, 'paid_centavos' => 40000]);
        $this->get($this->url($business, $court))->assertOk()->assertSee('26 to review · 26 ready to move')->assertSee('Page 1 of 2')->assertSee('Next');
        $this->get($this->url($business, $court, ['page' => 2]))->assertOk()->assertSee('Page 2 of 2')->assertSee('26 to review · 26 ready to move');
    }
}

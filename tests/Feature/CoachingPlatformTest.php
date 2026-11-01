<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Coach;
use App\Models\Court;
use App\Models\FeeSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoachingPlatformTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function setupLesson(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00', 'UTC'));
        $owner = User::factory()->create();
        $business = Business::factory()->create(['status' => 'approved', 'is_demo' => false, 'payment_instructions' => 'Pay test venue account.']);
        $business->users()->attach($owner, ['role' => 'owner']);
        $court = Court::factory()->create(['business_id' => $business->id]);
        $coach = Coach::factory()->create();
        $coach->businesses()->attach($business, ['status' => 'approved']);
        for ($day = 0; $day < 7; $day++) {
            $coach->availability()->create(['weekday' => $day, 'start_minute' => 540, 'end_minute' => 1020]);
        }FeeSetting::factory()->create(['amount_centavos' => 2500]);

        return [$owner, $business, $court, $coach];
    }

    private function data(Business $business, Court $court, array $overrides = []): array
    {
        return array_replace(['business_id' => $business->id, 'court_id' => $court->id, 'starts_at' => '2026-11-02T09:00', 'duration' => 60, 'customer_name' => 'Lesson Student', 'customer_contact' => '09171234567', 'request_key' => (string) Str::uuid(), 'accept_terms' => '1'], $overrides);
    }

    private function reserve(Business $business, Court $court, Coach $coach, array $overrides = []): Booking
    {
        $this->post(route('public.coaches.store', $coach), $this->data($business, $court, $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return Booking::query()->latest('id')->firstOrFail();
    }

    private function confirm(User $owner, Booking $booking): void
    {
        $proof = $booking->proofs()->create(['path' => 'private/test.webp', 'reference' => 'TRANSFER', 'status' => 'submitted']);
        $booking->update(['status' => 'awaiting_review']);
        $this->actingAs($owner)->patch(route('proofs.update', $proof), ['decision' => 'verify', 'received_amount' => '1000'])->assertSessionHasNoErrors();
    }

    public function test_coach_registration_creates_pending_profile_without_venue_privileges(): void
    {
        $this->post(route('coach.register.store'), ['name' => 'New Coach', 'email' => 'coach@example.test', 'password' => 'StrongCoaching123!', 'password_confirmation' => 'StrongCoaching123!', 'status' => 'approved', 'is_super_admin' => 1])->assertRedirect(route('coach.portal'));
        $coach = Coach::query()->sole();
        $this->assertSame('pending', $coach->status);
        $this->assertFalse($coach->user->is_super_admin);
        $this->assertFalse($coach->user->businesses()->exists());
        $this->get(route('coach.portal'))->assertOk()->assertSee('Profile pending');
    }

    public function test_existing_account_can_apply_and_only_admin_approves(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('coach.apply'))->assertRedirect(route('coach.portal'));
        $coach = Coach::query()->sole();
        $this->post(route('coach.apply'));
        $this->assertDatabaseCount('coaches', 1);
        $this->patch(route('coaches.approvals.update', $coach), ['status' => 'approved'])->assertForbidden();
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin)->get(route('coaches.approvals'))->assertOk()->assertSee($user->email);
        $this->patch(route('coaches.approvals.update', $coach), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $coach->fresh()->status);
    }

    public function test_guest_lesson_atomically_holds_coach_and_court_and_snapshots_both_prices(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $this->assertSame($coach->id, $booking->coach_id);
        $this->assertSame(100000, $booking->total_centavos);
        $this->assertSame(60000, $booking->coaching_centavos);
        $this->assertSame(2500, $booking->commission_centavos);
        $this->assertSame('held', $booking->status);
        $this->assertSame(0, $booking->paid_centavos);
        $this->assertDatabaseCount('inventory_claims', 2);
        $this->get(route('public.reservations.show', $booking->reference))->assertOk()->assertSee($coach->user->name)->assertSee('Coaching fee')->assertSee('1,000.00');
        $this->confirm($owner, $booking);
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_coach_cannot_be_booked_at_different_venues_at_the_same_time(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $this->reserve($business, $court, $coach);
        $other = Business::factory()->create(['status' => 'approved', 'payment_instructions' => 'Test payment']);
        $otherCourt = Court::factory()->create(['business_id' => $other->id]);
        $coach->businesses()->attach($other, ['status' => 'approved']);
        $this->post(route('public.coaches.store', $coach), $this->data($other, $otherCourt))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('inventory_claims', 2);
    }

    public function test_lesson_requires_available_coach_approved_affiliation_and_real_court(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $this->post(route('public.coaches.store', $coach), $this->data($business, $court, ['starts_at' => '2026-11-02T08:30']))->assertSessionHasErrors('starts_at');
        $coach->blocks()->create(['starts_at' => CarbonImmutable::parse('2026-11-02 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-02 02:00', 'UTC'), 'reason' => 'Private break']);
        $this->post(route('public.coaches.store', $coach), $this->data($business, $court))->assertSessionHasErrors('starts_at');
        $coach->businesses()->updateExistingPivot($business->id, ['status' => 'pending']);
        $this->post(route('public.coaches.store', $coach), $this->data($business, $court))->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_expiration_and_cancellation_release_coach_availability(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $first = $this->reserve($business, $court, $coach);
        $this->travel(16)->minutes();
        $second = $this->reserve($business, $court, $coach);
        $this->assertSame('expired', $first->fresh()->status);
        $this->actingAs($owner)->patch(route('bookings.update', $second), ['action' => 'cancel'])->assertRedirect();
        $third = $this->reserve($business, $court, $coach);
        $this->assertNotSame($third->id, $second->id);
        $this->assertDatabaseCount('inventory_claims', 2);
    }

    public function test_lesson_request_is_idempotent_and_payment_fields_cannot_be_injected(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $data = $this->data($business, $court);
        $this->post(route('public.coaches.store', $coach), $data)->assertSessionHasNoErrors();
        $this->post(route('public.coaches.store', $coach), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);
        $this->post(route('public.coaches.store', $coach), array_replace($data, ['request_key' => (string) Str::uuid(), 'paid_centavos' => 100000, 'coaching_centavos' => 1, 'total_centavos' => 1, 'coach_id' => $coach->id]))->assertSessionHasErrors(['paid_centavos', 'coaching_centavos', 'total_centavos', 'coach_id']);
    }

    public function test_profile_and_affiliation_require_independent_approval_and_do_not_add_memberships(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $coach->businesses()->detach();
        $this->actingAs($coach->user)->post(route('coach.affiliations.store'), ['business_id' => $business->id])->assertSessionHasNoErrors();
        $this->assertSame('pending', $coach->businesses()->first()->pivot->status);
        $this->patch(route('affiliations.update', [$business, $coach]), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($owner)->get(route('affiliations.index', $business))->assertOk()->assertSee($coach->user->email);
        $this->patch(route('affiliations.update', [$business, $coach]), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $coach->businesses()->first()->pivot->status);
        $this->assertFalse($coach->user->canAccessBusiness($business->id));
        $coach->update(['status' => 'pending']);
        $this->get(route('public.coaches.show', $coach))->assertNotFound();
    }

    public function test_portal_sections_render_and_coach_has_no_access_to_other_lessons_or_receipts(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $proof = $booking->proofs()->create(['path' => 'private/test.webp', 'reference' => 'REF']);
        $this->actingAs($coach->user);
        foreach (['lessons', 'profile', 'availability', 'venues', 'time-off'] as $tab) {
            $this->get(route('coach.portal', ['tab' => $tab, 'date' => '2026-11-02']))->assertOk();
        }$this->get(route('coach.lessons.show', $booking))->assertOk()->assertSee('Lesson Student');
        $this->get(route('bookings.show', $booking))->assertNotFound();
        $this->get(route('proofs.show', $proof))->assertNotFound();
        $this->actingAs(Coach::factory()->create()->user)->get(route('coach.lessons.show', $booking))->assertNotFound();
    }

    public function test_profile_rate_changes_preserve_existing_lesson_and_cannot_self_approve(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $coach->update(['status' => 'pending']);
        $this->actingAs($coach->user)->put(route('coach.profile'), ['bio' => 'A new coaching bio.', 'specialties' => 'Doubles strategy', 'hourly_rate' => '800.50', 'is_accepting' => 1, 'status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('pending', $coach->fresh()->status);
        $this->assertSame(80050, $coach->fresh()->hourly_rate_centavos);
        $this->assertSame(60000, $booking->fresh()->coaching_centavos);
        $this->assertSame(100000, $booking->fresh()->total_centavos);
    }

    public function test_weekly_hours_and_time_off_cannot_displace_existing_lesson(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $this->actingAs($coach->user);
        $days = array_fill(0, 7, ['enabled' => 1, 'start' => 540, 'end' => 1020]);
        $this->put(route('coach.availability'), ['days' => $days])->assertSessionHasNoErrors();
        $days[1]['enabled'] = 0;
        $this->put(route('coach.availability'), ['days' => $days])->assertSessionHasErrors('days');
        $this->post(route('coach.blocks.store'), ['starts_at' => '2026-11-02T09:30', 'ends_at' => '2026-11-02T10:30', 'reason' => 'Holiday'])->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('coach_blocks', 0);
        $this->assertDatabaseCount('coach_availabilities', 7);
    }

    public function test_time_off_is_private_and_owner_scoped_and_filters_public_slots(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $this->actingAs($coach->user)->post(route('coach.blocks.store'), ['starts_at' => '2026-11-02T09:00', 'ends_at' => '2026-11-02T10:00', 'reason' => 'Private appointment'])->assertSessionHasNoErrors();
        $block = $coach->blocks()->sole();
        $this->get(route('public.coaches.show', ['coach' => $coach, 'date' => '2026-11-02']))->assertOk()->assertDontSee('value="'.$court->id.'|2026-11-02T09:00"', false)->assertSee('value="'.$court->id.'|2026-11-02T10:00"', false)->assertDontSee('Private appointment');
        $this->actingAs(Coach::factory()->create()->user)->delete(route('coach.blocks.destroy', $block))->assertNotFound();
        $this->actingAs($coach->user)->delete(route('coach.blocks.destroy', $block))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('coach_blocks', 0);
    }

    public function test_attendance_and_private_notes_are_limited_to_own_paid_lessons(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $this->actingAs($coach->user)->put(route('coach.lessons.update', $booking), ['coach_notes' => 'Private student assessment'])->assertStatus(409);
        $this->confirm($owner, $booking);
        $this->actingAs($coach->user)->put(route('coach.lessons.update', $booking), ['coach_notes' => 'Private student assessment', 'lesson_attendance' => 'attended'])->assertSessionHasErrors('lesson_attendance');
        $this->travel(2)->days();
        $this->put(route('coach.lessons.update', $booking), ['coach_notes' => 'Private student assessment', 'lesson_attendance' => 'attended'])->assertSessionHasNoErrors();
        $this->get(route('coach.lessons.show', $booking))->assertOk()->assertSee('Private student assessment');
        $this->get(route('public.reservations.show', $booking->reference))->assertOk()->assertDontSee('Private student assessment');
        $this->actingAs($owner)->get(route('bookings.show', $booking))->assertOk()->assertDontSee('Private student assessment');
        $this->assertSame('attended', $booking->fresh()->lesson_attendance);
    }

    public function test_weather_move_requires_shared_coach_availability_and_keeps_fee(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $this->confirm($owner, $booking);
        $coach->blocks()->create(['starts_at' => CarbonImmutable::parse('2026-11-03 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-03 02:00', 'UTC'), 'reason' => 'Unavailable']);
        $data = ['court_id' => $court->id, 'starts_at' => '2026-11-03T09:00', 'reason' => 'rain', 'expected_version' => 0, 'request_key' => (string) Str::uuid()];
        $this->post(route('bookings.reschedule.store', $booking), $data)->assertSessionHasErrors('starts_at');
        $this->get(route('bookings.reschedule.edit', ['booking' => $booking, 'date' => '2026-11-03', 'court' => $court->id]))->assertOk()->assertDontSee('value="2026-11-03T09:00"', false)->assertSee('value="2026-11-03T10:00"', false);
        $data['starts_at'] = '2026-11-03T10:00';
        $this->post(route('bookings.reschedule.store', $booking), $data)->assertSessionHasNoErrors();
        $this->assertSame(60000, $booking->fresh()->coaching_centavos);
        $this->assertSame(100000, $booking->fresh()->total_centavos);
    }

    public function test_public_directory_hides_demo_suspended_and_unaffiliated_coaches(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $this->get(route('public.coaches.index'))->assertOk()->assertSee($coach->user->name);
        $business->update(['is_demo' => true]);
        $this->get(route('public.coaches.index'))->assertOk()->assertDontSee($coach->user->name);
        $this->get(route('public.coaches.show', $coach))->assertNotFound();
        $business->update(['is_demo' => false]);
        $coach->update(['status' => 'suspended']);
        $this->get(route('public.coaches.show', $coach))->assertNotFound();
    }

    public function test_coach_login_defaults_to_dedicated_portal(): void
    {
        $coach = Coach::factory()->create();
        $this->post(route('login.store'), ['email' => $coach->user->email, 'password' => 'password'])->assertRedirect(route('coach.portal'));
    }

    public function test_private_preview_renders_demo_affiliation_without_enabling_checkout(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $business->update(['is_demo' => true, 'status' => 'draft']);
        $this->actingAs($coach->user)->get(route('coach.preview', ['date' => '2026-11-02']))->assertOk()->assertSee('Private coach preview')->assertSee('noindex,nofollow', false)->assertSee('Coaching ₱600.00 + court ₱400.00')->assertSee('Continue to payment')->assertSee('disabled', false);
        $this->assertDatabaseCount('bookings', 0);
        $this->get(route('public.coaches.show', $coach))->assertNotFound();
        $this->get(route('coach.preview', ['business' => Business::factory()->create()->id]))->assertNotFound();
    }

    public function test_coach_pause_stops_new_lessons_but_keeps_weather_moves_available(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $this->confirm($owner, $booking);
        $coach->update(['is_accepting' => false]);
        $this->post(route('public.coaches.store', $coach), $this->data($business, $court, ['starts_at' => '2026-11-03T10:00']))->assertNotFound();
        $this->post(route('bookings.reschedule.store', $booking), ['court_id' => $court->id, 'starts_at' => '2026-11-03T10:00', 'reason' => 'rain', 'expected_version' => 0, 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertSame(1, $booking->fresh()->reschedule_version);
    }

    public function test_existing_court_reservation_prevents_lesson_without_creating_partial_booking(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $this->actingAs($owner)->post(route('bookings.store', $business), ['court_id' => $court->id, 'starts_at' => '2026-11-02T09:00', 'duration' => 60, 'customer_name' => 'Court Player', 'customer_contact' => 'Test phone', 'payment_method' => 'cash', 'paid_amount' => '400', 'request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->post(route('public.coaches.store', $coach), $this->data($business, $court))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('inventory_claims', 2);
        $this->assertSame(0, $coach->bookings()->count());
    }

    public function test_court_only_payment_cannot_confirm_combined_lesson(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $booking = $this->reserve($business, $court, $coach);
        $proof = $booking->proofs()->create(['path' => 'private/test.webp', 'reference' => 'TEST']);
        $booking->update(['status' => 'awaiting_review']);
        $this->actingAs($owner)->patch(route('proofs.update', $proof), ['decision' => 'verify', 'received_amount' => '400'])->assertSessionHasErrors('received_amount');
        $this->assertSame('awaiting_review', $booking->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paid_centavos);
        $this->assertSame('submitted', $proof->fresh()->status);
    }

    public function test_owner_cannot_approve_another_venue_affiliation_and_revocation_stops_new_lessons(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $other = Business::factory()->create();
        $coach->businesses()->attach($other, ['status' => 'pending']);
        $this->actingAs($owner)->patch(route('affiliations.update', [$other, $coach]), ['status' => 'approved'])->assertForbidden();
        $this->patch(route('affiliations.update', [$business, $coach]), ['status' => 'suspended'])->assertSessionHasNoErrors();
        $this->post(route('public.coaches.store', $coach), $this->data($business, $court))->assertNotFound();
    }

    public function test_coach_portal_defaults_to_upcoming_lessons_at_every_affiliated_venue(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $this->reserve($business, $court, $coach);
        $this->actingAs($coach->user)->get(route('coach.portal'))->assertOk()->assertSee('Upcoming lessons')->assertSee('Lesson Student')->assertSee($business->name);
    }

    public function test_time_off_rejects_a_past_local_time_even_when_its_utc_text_looks_future(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $this->actingAs($coach->user)->post(route('coach.blocks.store'), ['starts_at' => '2026-11-01T07:00', 'ends_at' => '2026-11-01T09:00', 'reason' => 'Already started'])->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('coach_blocks', 0);
    }

    public function test_public_lesson_court_must_belong_to_selected_affiliated_venue(): void
    {
        [$owner,$business,$court,$coach] = $this->setupLesson();
        $other = Court::factory()->create();
        $this->post(route('public.coaches.store', $coach), $this->data($business, $other))->assertNotFound();
        $this->assertDatabaseCount('bookings', 0);
    }
}

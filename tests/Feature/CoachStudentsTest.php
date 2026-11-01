<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Coach;
use App\Models\Court;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CoachStudentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function coach(): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 00:00', 'UTC'));
        $coach = Coach::factory()->create();
        $business = Business::factory()->create();
        $court = Court::factory()->create(['business_id' => $business->id]);
        $this->actingAs($coach->user);

        return [$coach, $business, $court];
    }

    private function lesson(Coach $coach, Business $business, Court $court, array $overrides = []): Booking
    {
        return Booking::factory()->create(array_replace(['coach_id' => $coach->id, 'business_id' => $business->id, 'court_id' => $court->id, 'customer_name' => 'Alex Player', 'customer_contact' => '09171234567', 'status' => 'completed', 'total_centavos' => 100000, 'paid_centavos' => 100000, 'coaching_centavos' => 60000, 'starts_at' => CarbonImmutable::parse('2026-11-02 01:00', 'UTC'), 'ends_at' => CarbonImmutable::parse('2026-11-02 02:00', 'UTC'), 'lesson_attendance' => 'attended'], $overrides));
    }

    public function test_roster_groups_own_paid_lessons_across_venues_and_shows_private_progress(): void
    {
        [$coach,$business,$court] = $this->coach();
        $first = $this->lesson($coach, $business, $court, ['coach_notes' => 'Improve split step timing.']);
        $other = Business::factory()->create();
        $otherCourt = Court::factory()->create(['business_id' => $other->id]);
        $this->lesson($coach, $other, $otherCourt, ['coach_notes' => 'Better footwork this session.']);
        $this->get(route('coach.students.index'))->assertOk()->assertSee('1 student record')->assertSee('Alex Player')->assertSee(route('coach.students.show', $first->id), false)->assertDontSee('Improve split step timing.');
        $this->get(route('coach.students.show', $first))->assertOk()->assertSee('Improve split step timing.')->assertSee('Better footwork this session.')->assertSee($business->name)->assertSee($other->name)->assertSee('2 bookings');
    }

    public function test_unpaid_holds_expired_and_cancelled_bookings_do_not_create_student_records(): void
    {
        [$coach,$business,$court] = $this->coach();
        foreach (['held', 'awaiting_review', 'expired', 'cancelled'] as $status) {
            $this->lesson($coach, $business, $court, ['status' => $status, 'customer_name' => 'Only '.$status]);
        }$this->lesson($coach, $business, $court, ['customer_name' => 'Unpaid historical', 'status' => 'confirmed', 'paid_centavos' => 0]);
        $this->get(route('coach.students.index'))->assertOk()->assertSee('Your student roster starts with a lesson.')->assertDontSee('Unpaid historical')->assertDontSee('Only held');
    }

    public function test_shared_contact_or_changed_contact_does_not_merge_different_booking_details(): void
    {
        [$coach,$business,$court] = $this->coach();
        $this->lesson($coach, $business, $court);
        $this->lesson($coach, $business, $court, ['customer_name' => 'Blair Player']);
        $this->lesson($coach, $business, $court, ['customer_contact' => 'alex@example.test']);
        $this->get(route('coach.students.index'))->assertOk()->assertSee('3 student records')->assertSee('Blair Player')->assertSee('alex@example.test');
    }

    public function test_search_is_limited_to_this_coach_and_accepts_name_or_contact(): void
    {
        [$coach,$business,$court] = $this->coach();
        $this->lesson($coach, $business, $court);
        $this->lesson($coach, $business, $court, ['customer_name' => 'Blair Player', 'customer_contact' => 'blair@example.test']);
        $this->lesson(Coach::factory()->create(), $business, $court, ['customer_name' => 'Other Coach Secret', 'coach_notes' => 'Other private notes']);
        $this->get(route('coach.students.index', ['search' => 'Alex']))->assertOk()->assertSee('Alex Player')->assertDontSee('Blair Player')->assertDontSee('Other Coach Secret');
        $this->get(route('coach.students.index', ['search' => 'blair@example.test']))->assertOk()->assertSee('Blair Player')->assertDontSee('Alex Player');
        $this->get(route('coach.students.index', ['search' => 'Unknown']))->assertOk()->assertSee('No matching student records.');
    }

    public function test_student_history_cannot_be_read_by_another_coach_or_venue_owner(): void
    {
        [$coach,$business,$court] = $this->coach();
        $booking = $this->lesson($coach, $business, $court, ['coach_notes' => 'Confidential progress']);
        $this->actingAs(Coach::factory()->create()->user)->get(route('coach.students.show', $booking))->assertNotFound();
        $owner = User::factory()->create();
        $business->users()->attach($owner, ['role' => 'owner']);
        $this->actingAs($owner)->get(route('coach.students.show', $booking))->assertNotFound();
    }

    public function test_history_includes_nonactive_bookings_but_paid_summary_excludes_them(): void
    {
        [$coach,$business,$court] = $this->coach();
        $booking = $this->lesson($coach, $business, $court);
        $this->lesson($coach, $business, $court, ['status' => 'cancelled', 'coach_notes' => 'Previous cancelled session note']);
        $this->lesson($coach, $business, $court, ['status' => 'held', 'paid_centavos' => 0, 'coach_notes' => null]);
        $this->get(route('coach.students.show', $booking))->assertOk()->assertSee('3 bookings')->assertSee('Previous cancelled session note')->assertSee('cancelled')->assertSee('held')->assertSee('<dd>1</dd>', false);
    }

    public function test_progress_updates_and_attendance_corrections_are_reflected_in_history(): void
    {
        [$coach,$business,$court] = $this->coach();
        $booking = $this->lesson($coach, $business, $court, ['lesson_attendance' => null]);
        $this->put(route('coach.lessons.update', $booking), ['coach_notes' => 'Work on third shot drops.', 'lesson_attendance' => 'attended'])->assertSessionHasNoErrors();
        $this->get(route('coach.students.show', $booking))->assertOk()->assertSee('Work on third shot drops.')->assertSee('attended');
        $this->put(route('coach.lessons.update', $booking), ['coach_notes' => 'Corrected assessment.', 'lesson_attendance' => ''])->assertSessionHasNoErrors();
        $this->assertNull($booking->fresh()->lesson_attendance);
        $this->get(route('coach.students.show', $booking))->assertOk()->assertSee('Corrected assessment.')->assertDontSee('Work on third shot drops.');
    }

    public function test_student_details_and_notes_are_escaped_and_reads_do_not_change_records(): void
    {
        [$coach,$business,$court] = $this->coach();
        $booking = $this->lesson($coach, $business, $court, ['customer_name' => '<b>Alex</b>', 'coach_notes' => '<script>alert("private")</script>']);
        $this->get(route('coach.students.show', $booking))->assertOk()->assertSee('<b>Alex</b>')->assertSee('<script>alert("private")</script>')->assertDontSee('<script>alert("private")</script>', false);
        $this->assertDatabaseCount('audit_events', 0);
        $this->assertDatabaseCount('booking_reschedules', 0);
        $this->assertSame(100000, $booking->fresh()->paid_centavos);
    }

    public function test_pending_only_student_history_is_not_exposed_and_search_is_validated(): void
    {
        [$coach,$business,$court] = $this->coach();
        $booking = $this->lesson($coach, $business, $court, ['status' => 'held', 'paid_centavos' => 0]);
        $this->get(route('coach.students.show', $booking))->assertNotFound();
        $this->get(route('coach.students.index', ['search' => str_repeat('x', 121)]))->assertSessionHasErrors('search');
    }

    public function test_student_and_history_pagination_preserves_distinct_groups_and_search(): void
    {
        [$coach,$business,$court] = $this->coach();
        for ($index = 0; $index < 21; $index++) {
            $this->lesson($coach, $business, $court, ['customer_name' => 'Roster Student '.sprintf('%02d', $index)]);
        }$this->get(route('coach.students.index', ['search' => 'Roster Student']))->assertOk()->assertSee('21 student records')->assertSee('Page 1 of 2')->assertSee('search=Roster', false);
        $this->get(route('coach.students.index', ['search' => 'Roster Student', 'page' => 2]))->assertOk()->assertSee('Roster Student 20')->assertSee('Page 2 of 2');
        $first = $this->lesson($coach, $business, $court);
        for ($index = 0; $index < 15; $index++) {
            $this->lesson($coach, $business, $court);
        }$this->get(route('coach.students.show', $first))->assertOk()->assertSee('16 bookings')->assertSee('Page 1 of 2');
    }
}

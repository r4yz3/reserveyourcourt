<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Court;
use App\Models\FeeSetting;
use App\Models\PaymentProof;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BookingPlatformTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_partial_instapay_receipt_cannot_be_approved(): void
    {
        [$user, $business, $court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'status' => 'awaiting_review', 'payment_method' => 'instapay', 'hold_expires_at' => now()->addHour()]);
        $proof = PaymentProof::factory()->create(['booking_id' => $booking->id]);

        $this->actingAs($user)->patch(route('proofs.update', $proof), ['decision' => 'verify', 'received_amount' => '100'])
            ->assertSessionHasErrors('received_amount');

        $this->assertSame('awaiting_review', $booking->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paid_centavos);
        $this->assertSame('submitted', $proof->fresh()->status);
    }

    #[TestWith(['0'])]
    #[TestWith(['100'])]
    #[TestWith(['401'])]
    public function test_cash_reservation_requires_the_exact_full_rental_payment(string $amount): void
    {
        [$user, $business, $court] = $this->venue();

        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['paid_amount' => $amount]))
            ->assertSessionHasErrors('paid_amount');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('inventory_claims', 0);
    }

    public function test_walk_in_creation_is_rejected_even_when_payment_is_full(): void
    {
        [$user, $business, $court] = $this->venue();

        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['source' => 'walk_in']))
            ->assertSessionHasErrors('source');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('inventory_claims', 0);
        $this->get(route('bookings.create', $business))->assertOk()->assertDontSee('name="source"', false)->assertDontSee('Walk-in');
    }

    public function test_unverified_instapay_payment_never_confirms_a_reservation(): void
    {
        [$user, $business, $court] = $this->venue();

        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['payment_method' => 'instapay']))
            ->assertSessionHasNoErrors()->assertRedirect();

        $booking = Booking::query()->sole();
        $this->assertSame('held', $booking->status);
        $this->assertSame(0, $booking->paid_centavos);
        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('Payment pending · not confirmed');
    }

    public function test_reservation_source_is_assigned_by_the_server_when_form_omits_it(): void
    {
        [$user, $business, $court] = $this->venue();
        $payload = $this->payload($court);
        unset($payload['source']);

        $this->actingAs($user)->post(route('bookings.store', $business), $payload)->assertSessionHasNoErrors();

        $booking = Booking::query()->sole();
        $this->assertSame('online', $booking->source);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame(40000, $booking->paid_centavos);
    }

    public function test_selecting_a_schedule_slot_prefills_the_booking_form(): void
    {
        [$user, $business, $court] = $this->venue();

        $this->actingAs($user)->get(route('bookings.create', ['business' => $business, 'court' => $court->id, 'start' => '2026-11-02T14:30']))
            ->assertOk()
            ->assertViewHas('selectedCourt', (string) $court->id)
            ->assertSee('value="2026-11-02T14:30"', false)
            ->assertSee('Reservation summary');
    }

    public function test_invalid_schedule_prefill_is_rejected(): void
    {
        [$user, $business] = $this->venue();

        $this->actingAs($user)->get(route('bookings.create', ['business' => $business, 'start' => 'not-a-date']))
            ->assertSessionHasErrors('start');
    }

    public function test_retried_cash_payment_is_not_recorded_twice(): void
    {
        [$user, $business, $court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id]);
        $payment = ['action' => 'cash', 'amount' => '100.25', 'request_key' => (string) Str::uuid()];

        $this->actingAs($user)->patch(route('bookings.update', $booking), $payment)->assertRedirect();
        $this->patch(route('bookings.update', $booking), $payment)->assertRedirect();

        $this->assertSame(10025, $booking->fresh()->paid_centavos);
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_staff_cannot_add_courts_but_can_operate_their_schedule(): void
    {
        [$user, $business] = $this->venue();
        $business->users()->updateExistingPivot($user->id, ['role' => 'staff']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->get(route('venues.edit', $business))->assertForbidden();
        $this->post(route('courts.store', $business), ['name' => 'Court 4', 'type' => 'Indoor', 'hourly_rate' => '400', 'opens_at' => 6, 'closes_at' => 22])->assertForbidden();

        $this->assertDatabaseCount('courts', 1);
    }

    public function test_login_and_logout_use_real_session_authentication(): void
    {
        $user = User::factory()->create(['password' => 'TestCourtPassword!']);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'TestCourtPassword!'])->assertRedirect(route('play.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_receipt_upload_is_converted_to_private_webp_and_waits_for_verification(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('Run with .runtime/php/php.exe to exercise real WebP conversion.');
        }
        [$user, $business, $court] = $this->venue();
        Storage::fake('local');
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'status' => 'held', 'payment_method' => 'instapay', 'hold_expires_at' => now()->addMinutes(15)]);
        $file = UploadedFile::fake()->image('receipt.png', 3000, 1200);

        $this->actingAs($user)->post(route('proofs.store', $booking), ['reference' => 'BANK-123456', 'receipt' => $file])->assertSessionHasNoErrors()->assertRedirect();

        $proof = PaymentProof::query()->sole();
        Storage::disk('local')->assertExists($proof->path);
        $image = getimagesizefromstring(Storage::disk('local')->get($proof->path));
        $this->assertSame('image/webp', $image['mime']);
        $this->assertSame(2400, $image[0]);
        $this->assertSame(960, $image[1]);
        $this->assertSame('awaiting_review', $booking->fresh()->status);
        $this->assertSame(0, $booking->fresh()->paid_centavos);
    }

    public function test_non_image_receipt_is_rejected_without_a_proof_record(): void
    {
        [$user, $business, $court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'status' => 'confirmed', 'payment_method' => 'instapay']);
        $file = UploadedFile::fake()->createWithContent('receipt.jpg', '<?php echo "not an image";');

        $this->actingAs($user)->post(route('proofs.store', $booking), ['reference' => 'BANK-123456', 'receipt' => $file])->assertSessionHasErrors('receipt');

        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_authenticated_schedule_responses_cannot_be_shared_cached(): void
    {
        [$user] = $this->venue();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    private function venue(bool $withFee = true): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user, ['role' => 'owner']);
        $court = Court::factory()->create(['business_id' => $business->id]);
        if ($withFee) {
            FeeSetting::factory()->create(['changed_by' => $user->id]);
        }

        return [$user, $business, $court];
    }

    private function payload(Court $court, array $overrides = []): array
    {
        return array_replace(['court_id' => $court->id, 'customer_name' => 'Test player', 'customer_contact' => '09171234567', 'starts_at' => '2026-11-02T09:00', 'duration' => 60, 'source' => 'online', 'payment_method' => 'cash', 'paid_amount' => (($overrides['payment_method'] ?? 'cash') === 'instapay' ? '0' : '400'), 'request_key' => (string) Str::uuid()], $overrides);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_super_admin = true;
        $user->save();

        return $user;
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_owner_can_render_schedule_and_booking_forms(): void
    {
        [$user,$business] = $this->venue();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Court schedule')->assertSee($business->name);
        $this->get(route('bookings.create', $business))->assertOk()->assertSee('Add booking');
        $this->get(route('venues.edit', $business))->assertOk();
    }

    public function test_fully_paid_reservation_prices_and_fee_are_calculated_server_side(): void
    {
        [$user,$business,$court] = $this->venue();
        FeeSetting::factory()->create(['amount_centavos' => 2500]);
        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['duration' => 90, 'paid_amount' => '600', 'commission_centavos' => 99999, 'total_centavos' => 1]))->assertSessionHasNoErrors()->assertRedirect();
        $booking = Booking::query()->sole();
        $this->assertSame(2500, $booking->commission_centavos);
        $this->assertSame(60000, $booking->total_centavos);
        $this->assertSame(60000, $booking->paid_centavos);
        $this->assertDatabaseCount('inventory_claims', 3);
        $this->assertDatabaseHas('audit_events', ['action' => 'booking.created']);
        $this->get(route('bookings.show', $booking))->assertOk()->assertSee('Saved at booking');
    }

    public function test_online_booking_requires_a_configured_fee(): void
    {
        [$user,$business,$court] = $this->venue(false);
        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['source' => 'online']))->assertSessionHasErrors(['source']);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('inventory_claims', 0);
    }

    public function test_only_super_admin_can_edit_fees_and_old_bookings_keep_the_fee(): void
    {
        [$owner,$business,$court] = $this->venue(false);
        $this->actingAs($owner)->put(route('fees.update'), ['amount' => '25'])->assertForbidden();
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('fees.edit'))->assertOk();
        $this->put(route('fees.update'), ['amount' => '25.50'])->assertSessionHasNoErrors()->assertRedirect();
        $this->actingAs($owner)->post(route('bookings.store', $business), $this->payload($court, ['source' => 'online']))->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->actingAs($admin)->put(route('fees.update'), ['amount' => '40'])->assertSessionHasNoErrors();
        $this->assertSame(2550, $booking->fresh()->commission_centavos);
        $this->assertDatabaseCount('fee_settings', 2);
        $this->assertDatabaseHas('audit_events', ['action' => 'fee.changed', 'user_id' => $admin->id]);
    }

    public function test_overlapping_booking_is_rejected_but_adjacent_booking_is_allowed(): void
    {
        [$user,$business,$court] = $this->venue();
        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court))->assertSessionHasNoErrors();
        $this->post(route('bookings.store', $business), $this->payload($court, ['starts_at' => '2026-11-02T09:30']))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('bookings', 1);
        $this->post(route('bookings.store', $business), $this->payload($court, ['starts_at' => '2026-11-02T10:00']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_duplicate_submission_returns_same_booking(): void
    {
        [$user,$business,$court] = $this->venue();
        $payload = $this->payload($court);
        $this->actingAs($user)->post(route('bookings.store', $business), $payload)->assertSessionHasNoErrors();
        $this->post(route('bookings.store', $business), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('inventory_claims', 2);
    }

    public function test_cross_business_access_cannot_read_or_write_bookings_or_receipts(): void
    {
        [$user,$business,$court] = $this->venue();
        $other = Business::factory()->create();
        $otherCourt = Court::factory()->create(['business_id' => $other->id]);
        $booking = Booking::factory()->create(['business_id' => $other->id, 'court_id' => $otherCourt->id]);
        $proof = PaymentProof::factory()->create(['booking_id' => $booking->id]);
        $this->actingAs($user)->get(route('bookings.show', $booking))->assertNotFound();
        $this->get(route('proofs.show', $proof))->assertNotFound();
        $this->get(route('dashboard', ['business' => $other->id]))->assertNotFound();
        $this->post(route('bookings.store', $business), $this->payload($otherCourt))->assertNotFound();
        $this->patch(route('bookings.update', $booking), ['action' => 'cancel'])->assertNotFound();
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_cancel_releases_inventory_without_erasing_recorded_payment(): void
    {
        [$user,$business,$court] = $this->venue();
        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['paid_amount' => '400']))->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->patch(route('bookings.update', $booking), ['action' => 'cancel'])->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(40000, $booking->fresh()->paid_centavos);
        $this->assertDatabaseCount('inventory_claims', 0);
        $this->post(route('bookings.store', $business), $this->payload($court))->assertSessionHasNoErrors();
    }

    public function test_expired_online_hold_is_reclaimed_during_checkout(): void
    {
        [$user,$business,$court] = $this->venue();
        FeeSetting::factory()->create();
        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['source' => 'online', 'payment_method' => 'instapay']))->assertSessionHasNoErrors();
        $original = Booking::query()->sole();
        $this->assertSame('held', $original->status);
        $this->travel(16)->minutes();
        $this->post(route('bookings.store', $business), $this->payload($court))->assertSessionHasNoErrors();
        $this->assertSame('expired', $original->fresh()->status);
        $this->assertDatabaseCount('inventory_claims', 2);
    }

    public function test_expiry_command_releases_review_holds(): void
    {
        [$user,$business,$court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'status' => 'awaiting_review', 'hold_expires_at' => now()->subMinute()]);
        DB::table('inventory_claims')->insert(['court_id' => $court->id, 'booking_id' => $booking->id, 'starts_at' => $booking->starts_at]);
        $this->artisan('bookings:expire')->assertSuccessful();
        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertDatabaseCount('inventory_claims', 0);
    }

    public function test_cash_recording_rejects_overpayment(): void
    {
        [$user,$business,$court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id]);
        $this->actingAs($user);
        $this->patch(route('bookings.update', $booking), ['action' => 'cash', 'amount' => '100.25', 'request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame(10025, $booking->fresh()->paid_centavos);
        $this->patch(route('bookings.update', $booking), ['action' => 'cash', 'amount' => '500', 'request_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(10025, $booking->fresh()->paid_centavos);
    }

    public function test_instapay_cannot_be_marked_paid_when_creating_a_booking(): void
    {
        [$user,$business,$court] = $this->venue();
        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['payment_method' => 'instapay', 'paid_amount' => '100']))->assertSessionHasErrors('paid_amount');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_receipt_review_checks_state_and_cannot_be_repeated(): void
    {
        [$user,$business,$court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'status' => 'awaiting_review', 'payment_method' => 'instapay', 'hold_expires_at' => now()->addHour()]);
        $proof = PaymentProof::factory()->create(['booking_id' => $booking->id]);
        $this->actingAs($user)->patch(route('proofs.update', $proof), ['decision' => 'verify', 'received_amount' => '400'])->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(40000, $booking->fresh()->paid_centavos);
        $this->assertSame('verified', $proof->fresh()->status);
        $this->patch(route('proofs.update', $proof), ['decision' => 'verify', 'received_amount' => '400'])->assertConflict();
    }

    public function test_expired_receipt_cannot_confirm_a_released_booking(): void
    {
        [$user,$business,$court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'status' => 'awaiting_review', 'payment_method' => 'instapay', 'hold_expires_at' => now()->subMinute()]);
        $proof = PaymentProof::factory()->create(['booking_id' => $booking->id]);
        $this->actingAs($user)->patch(route('proofs.update', $proof), ['decision' => 'verify', 'received_amount' => '400'])->assertConflict();
        $this->assertSame(0, $booking->fresh()->paid_centavos);
    }

    public function test_receipts_are_served_privately_to_authorized_staff(): void
    {
        [$user,$business,$court] = $this->venue();
        Storage::fake('local');
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id]);
        $proof = PaymentProof::factory()->create(['booking_id' => $booking->id]);
        Storage::disk('local')->put($proof->path, 'test receipt');
        $response = $this->actingAs($user)->get(route('proofs.show', $proof));
        $response->assertOk()->assertHeader('Content-Type', 'image/webp');
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_invalid_times_are_rejected_without_allocating_inventory(): void
    {
        [$user,$business,$court] = $this->venue();
        $this->actingAs($user)->post(route('bookings.store', $business), $this->payload($court, ['starts_at' => '2026-11-02T09:15']))->assertSessionHasErrors('starts_at');
        $this->post(route('bookings.store', $business), $this->payload($court, ['starts_at' => '2026-11-02T22:00']))->assertSessionHasErrors('starts_at');
        $this->assertDatabaseCount('inventory_claims', 0);
    }

    public function test_customer_names_are_html_escaped(): void
    {
        [$user,$business,$court] = $this->venue();
        $booking = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id, 'customer_name' => '<script>alert(1)</script>']);
        $this->actingAs($user)->get(route('bookings.show', $booking))->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_invalid_fee_is_rejected_and_zero_is_an_explicit_waiver(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('fees.update'), ['amount' => '-1'])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('fee_settings', 0);
        $this->put(route('fees.update'), ['amount' => '0'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fee_settings', ['amount_centavos' => 0]);
    }
}

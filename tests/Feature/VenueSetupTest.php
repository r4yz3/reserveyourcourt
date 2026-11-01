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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueSetupTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_super_admin = true;
        $user->save();

        return $user;
    }

    private function venue(string $role = 'owner'): array
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user, ['role' => $role]);

        return [$user, $business];
    }

    private function profile(Business $business, array $overrides = []): array
    {
        return array_replace(['name' => $business->name, 'city' => $business->city, 'address' => '12 Court Street, Quezon City', 'description' => 'Indoor courts and shaded seating.', 'payment_instructions' => 'Sample bank: Venue Account. Verify receiving details with the venue.'], $overrides);
    }

    public function test_admin_creates_a_draft_business_and_a_new_owner_without_privilege_escalation(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('businesses.store'), ['name' => 'Test Court Club', 'city' => 'Pasig', 'owner_mode' => 'new', 'owner_email' => 'Owner@club.test', 'owner_name' => 'Venue Owner', 'password' => 'StrongPassword123!', 'password_confirmation' => 'StrongPassword123!', 'is_super_admin' => true, 'status' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();
        $business = Business::query()->sole();
        $owner = User::query()->where('email', 'owner@club.test')->sole();
        $this->assertSame('draft', $business->status);
        $this->assertFalse($owner->is_super_admin);
        $this->assertTrue(Hash::check('StrongPassword123!', $owner->password));
        $this->assertDatabaseHas('business_user', ['business_id' => $business->id, 'user_id' => $owner->id, 'role' => 'owner']);
        $this->assertDatabaseHas('audit_events', ['action' => 'business.created', 'user_id' => $admin->id]);
    }

    public function test_existing_owner_assignment_does_not_overwrite_credentials(): void
    {
        $owner = User::factory()->create();
        $password = $owner->password;
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('businesses.store'), ['name' => 'Court Club', 'city' => 'Cebu', 'owner_mode' => 'existing', 'owner_email' => $owner->email, 'password' => 'not-used', 'owner_name' => 'Not used'])->assertSessionHasNoErrors();
        $this->assertSame($password, $owner->fresh()->password);
        $this->assertSame(2, User::query()->count());
        $this->assertTrue($owner->canManageBusiness(Business::query()->sole()->id));
    }

    public function test_business_creation_is_admin_only(): void
    {
        [$owner] = $this->venue();
        $this->actingAs($owner)->get(route('businesses.index'))->assertForbidden();
        $this->get(route('businesses.create'))->assertForbidden();
        $this->post(route('businesses.store'), ['name' => 'Unauthorized'])->assertForbidden();
        $this->assertDatabaseCount('businesses', 1);
    }

    public function test_invalid_new_owner_password_does_not_create_accounts_or_flash_passwords(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('businesses.store'), ['name' => 'Court Club', 'city' => 'Cebu', 'owner_mode' => 'new', 'owner_email' => 'new@club.test', 'owner_name' => 'New Owner', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password')->assertSessionMissing('_old_input.password')->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('businesses', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_owner_updates_profile_but_cannot_change_approval_or_other_tenant(): void
    {
        [$owner,$business] = $this->venue();
        $other = Business::factory()->create();
        $this->actingAs($owner)->put(route('venues.update', $business), $this->profile($business, ['status' => 'approved']))->assertSessionHasNoErrors();
        $this->assertSame('draft', $business->fresh()->status);
        $this->assertSame('12 Court Street, Quezon City', $business->fresh()->address);
        $this->put(route('venues.update', $other), $this->profile($other))->assertNotFound();
        $this->patch(route('businesses.update', $business), ['status' => 'approved'])->assertForbidden();
    }

    public function test_staff_cannot_change_venue_profile_or_upload_photos(): void
    {
        [$staff,$business] = $this->venue('staff');
        $this->actingAs($staff)->put(route('venues.update', $business), $this->profile($business))->assertForbidden();
        $this->post(route('venue-photo.store', $business))->assertForbidden();
    }

    public function test_admin_approval_requires_ready_real_venue(): void
    {
        $admin = $this->admin();
        $business = Business::factory()->create();
        $this->actingAs($admin)->patch(route('businesses.update', $business), ['status' => 'approved'])->assertSessionHasErrors('status');
        $business->update($this->profile($business));
        Court::factory()->create(['business_id' => $business->id]);
        $this->patch(route('businesses.update', $business), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $business->fresh()->status);
        $business->update(['is_demo' => true]);
        $this->patch(route('businesses.update', $business), ['status' => 'approved'])->assertSessionHasErrors('status');
    }

    public function test_removing_required_details_returns_approved_business_to_draft(): void
    {
        [$owner,$business] = $this->venue();
        $business->update(['status' => 'approved']);
        $this->actingAs($owner)->put(route('venues.update', $business), $this->profile($business, ['payment_instructions' => '']))->assertSessionHasNoErrors();
        $this->assertSame('draft', $business->fresh()->status);
    }

    public function test_suspension_blocks_new_bookings_and_preserves_existing_ones(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01 00:00:00', 'UTC'));
        [$owner,$business] = $this->venue();
        $court = Court::factory()->create(['business_id' => $business->id]);
        FeeSetting::factory()->create();
        $existing = Booking::factory()->create(['business_id' => $business->id, 'court_id' => $court->id]);
        $business->update(['status' => 'suspended']);
        $this->actingAs($owner)->post(route('bookings.store', $business), ['court_id' => $court->id, 'customer_name' => 'Test player', 'customer_contact' => '09171234567', 'starts_at' => '2026-11-02T14:00', 'duration' => 60, 'payment_method' => 'cash', 'paid_amount' => '400', 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('business');
        $this->assertDatabaseCount('bookings', 1);
        $this->get(route('bookings.show', $existing))->assertOk();
    }

    public function test_photo_upload_is_resized_to_webp_and_authorized_only(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP required.');
        }
        [$owner,$business] = $this->venue();
        Storage::fake('local');
        $this->actingAs($owner)->post(route('venue-photo.store', $business), ['photo' => UploadedFile::fake()->image('court.jpg', 3200, 1600)])->assertSessionHasNoErrors()->assertRedirect();
        $business->refresh();
        Storage::disk('local')->assertExists($business->photo_path);
        $image = getimagesizefromstring(Storage::disk('local')->get($business->photo_path));
        $this->assertSame('image/webp', $image['mime']);
        $this->assertSame(1920, $image[0]);
        $this->assertSame(960, $image[1]);
        $this->get(route('venue-photo.show', $business))->assertOk()->assertHeader('Content-Type', 'image/webp');
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get(route('venue-photo.show', $business))->assertNotFound();
        $this->post(route('venue-photo.store', $business))->assertNotFound();
    }

    public function test_replacing_or_removing_photo_cleans_up_old_objects(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('GD with WebP required.');
        }
        [$owner,$business] = $this->venue();
        Storage::fake('local');
        $old = 'venue-photos/'.$business->id.'/old.webp';
        Storage::disk('local')->put($old, 'old');
        $business->update(['photo_path' => $old, 'photo_disk' => 'local']);
        $this->actingAs($owner)->post(route('venue-photo.store', $business), ['photo' => UploadedFile::fake()->image('new.png', 100, 50)])->assertSessionHasNoErrors();
        Storage::disk('local')->assertMissing($old);
        $new = $business->fresh()->photo_path;
        $this->delete(route('venue-photo.destroy', $business))->assertRedirect();
        Storage::disk('local')->assertMissing($new);
        $this->assertNull($business->fresh()->photo_path);
    }

    public function test_non_image_photo_is_rejected_without_storing_a_cover(): void
    {
        [$owner,$business] = $this->venue();
        $this->actingAs($owner)->post(route('venue-photo.store', $business), ['photo' => UploadedFile::fake()->createWithContent('photo.jpg', 'not an image')])->assertSessionHasErrors('photo');
        $this->assertNull($business->fresh()->photo_path);
    }

    public function test_business_filters_do_not_expose_unrelated_results(): void
    {
        $admin = $this->admin();
        Business::factory()->create(['name' => 'Pasig Club', 'city' => 'Pasig', 'status' => 'approved']);
        Business::factory()->create(['name' => 'Cebu Club', 'city' => 'Cebu']);
        $this->actingAs($admin)->get(route('businesses.index', ['search' => 'Pasig', 'status' => 'approved']))->assertOk()->assertSee('Pasig Club')->assertDontSee('Cebu Club');
        $this->get(route('businesses.create'))->assertOk()->assertSee('Business owner');
    }
}

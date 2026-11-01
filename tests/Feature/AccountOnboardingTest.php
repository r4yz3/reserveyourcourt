<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Business;
use App\Models\Coach;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AccountOnboardingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function signUpDetails(): array
    {
        return ['name' => 'Test Person', 'email' => 'test.person@example.test', 'password' => 'TestPassword123', 'password_confirmation' => 'TestPassword123'];
    }

    public function test_signing_up_creates_a_plain_account_with_no_coach_or_venue_role(): void
    {
        $this->post(route('register.store'), $this->signUpDetails() + ['is_super_admin' => 1])->assertRedirect(route('play.dashboard'));

        $user = User::query()->sole();
        $this->assertFalse($user->is_super_admin);
        $this->assertFalse($user->coach()->exists());
        $this->assertFalse($user->businesses()->exists());
        $this->assertSame('play.dashboard', $user->homeRoute());
    }

    public function test_sign_up_passwords_need_at_least_8_characters_with_letters_and_numbers(): void
    {
        foreach (['abcdef1', 'abcdefgh', '12345678'] as $rejected) {
            $this->post(route('register.store'), ['password' => $rejected, 'password_confirmation' => $rejected] + $this->signUpDetails())->assertSessionHasErrors('password');
        }
        $this->assertDatabaseCount('users', 0);

        $this->post(route('register.store'), ['password' => 'abcdefg1', 'password_confirmation' => 'abcdefg1'] + $this->signUpDetails())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_the_old_player_sign_up_address_redirects_to_the_single_form(): void
    {
        $this->get('/play/register')->assertRedirect('/register');
        $this->get(route('register'))->assertOk()->assertSee('Create account');
    }

    public function test_signing_up_from_a_venue_link_continues_to_the_venue_form(): void
    {
        $this->get(route('venue.create'))->assertRedirect(route('login'));

        $this->post(route('register.store'), $this->signUpDetails())->assertRedirect(route('venue.create'));
    }

    public function test_dashboard_offers_coaching_and_venues_until_the_user_has_them(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('play.dashboard'))->assertOk()->assertSee('Become a coach')->assertSee('Add your venue')->assertSee(route('venue.create'));

        Coach::factory()->create(['user_id' => $user->id]);
        Business::factory()->create()->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)->get(route('play.dashboard'))->assertOk()->assertSee('Open coach workspace')->assertSee('Open venue workspace')->assertDontSee('Become a coach')->assertDontSee('Add your venue');
    }

    public function test_coach_start_page_hides_links_that_need_a_coach_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('coach.portal'))->assertOk()->assertSee('Create my coach profile')->assertDontSee(route('coach.students.index'))->assertDontSee(route('coach.preview'));
        $this->actingAs($user)->post(route('coach.apply'));
        $this->actingAs($user)->get(route('coach.portal'))->assertOk()->assertSee(route('coach.students.index'))->assertSee(route('coach.preview'));
    }

    public function test_a_user_can_create_a_draft_venue_and_becomes_its_owner(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('venue.store'), ['name' => 'Test Courts', 'city' => 'Test City', 'address' => '1 Test Street']);

        $business = Business::query()->sole();
        $response->assertRedirect(route('venues.edit', $business));
        $this->assertSame(['Test Courts', 'Test City', '1 Test Street', 'draft'], [$business->name, $business->city, $business->address, $business->status]);
        $this->assertTrue($user->canManageBusiness($business->id));
        $this->assertSame('business.created', AuditEvent::query()->where('business_id', $business->id)->sole()->action);
        $this->actingAs($user)->get(route('venues.edit', $business))->assertOk();
    }

    public function test_venue_owners_can_get_back_to_their_player_area(): void
    {
        $user = User::factory()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('My games')->assertSee(route('play.dashboard'));
    }

    public function test_a_new_venue_cannot_approve_itself_or_appear_publicly(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('venue.store'), ['name' => 'Test Courts', 'city' => 'Test City', 'status' => 'approved', 'is_demo' => 1, 'payment_instructions' => 'Test instructions']);

        $business = Business::query()->sole();
        $this->assertSame(['draft', false, null], [$business->status, $business->is_demo, $business->payment_instructions]);
        $this->actingAs($user)->patch(route('businesses.update', $business), ['status' => 'approved'])->assertForbidden();
        $this->assertSame('draft', $business->fresh()->status);
        $this->get(route('public.venues.show', $business))->assertNotFound();
        $this->get(route('public.venues.index'))->assertDontSee('Test Courts');
    }

    public function test_venue_details_are_required(): void
    {
        $this->actingAs(User::factory()->create())->post(route('venue.store'), [])->assertSessionHasErrors(['name', 'city']);

        $this->assertDatabaseCount('businesses', 0);
    }
}

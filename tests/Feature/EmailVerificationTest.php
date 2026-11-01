<?php

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function signUpDetails(): array
    {
        return ['name' => 'Test Person', 'email' => 'test.person@example.test', 'password' => 'TestPassword123', 'password_confirmation' => 'TestPassword123'];
    }

    private function verificationUrl(User $user, ?string $email = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($email ?? $user->email)]);
    }

    public function test_signing_up_sends_a_confirmation_email_without_blocking_the_player_area(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->signUpDetails())->assertRedirect(route('play.dashboard'));

        $user = User::query()->sole();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get(route('play.dashboard'))->assertOk();
    }

    public function test_sign_up_still_succeeds_when_the_confirmation_email_cannot_be_sent(): void
    {
        Notification::shouldReceive('send')->andThrow(new RuntimeException('Mail server unavailable'));

        $this->post(route('register.store'), $this->signUpDetails())->assertRedirect(route('play.dashboard'));

        $this->assertAuthenticatedAs(User::query()->sole());
    }

    public function test_unconfirmed_accounts_cannot_create_a_venue(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('venue.create'))->assertRedirect(route('verification.notice'));
        $this->actingAs($user)->post(route('venue.store'), ['name' => 'Test Courts', 'city' => 'Test City'])->assertRedirect(route('verification.notice'));

        $this->assertDatabaseCount('businesses', 0);
        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee($user->email);
    }

    public function test_confirming_returns_the_user_to_the_venue_form(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get(route('venue.create'));

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect(route('venue.create'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->actingAs($user)->get(route('venue.create'))->assertOk();
    }

    public function test_confirmation_links_only_work_for_the_right_account_and_signature(): void
    {
        $user = User::factory()->unverified()->create();
        $other = User::factory()->unverified()->create();

        $this->actingAs($other)->get($this->verificationUrl($user))->assertForbidden();
        $this->actingAs($user)->get($this->verificationUrl($user, 'someone.else@example.test'))->assertForbidden();
        $this->actingAs($user)->get(route('verification.verify', ['id' => $user->id, 'hash' => sha1($user->email)]))->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_a_new_link_is_only_sent_to_unconfirmed_accounts(): void
    {
        Notification::fake();
        $unverified = User::factory()->unverified()->create();
        $verified = User::factory()->create();

        $this->actingAs($unverified)->post(route('verification.send'))->assertSessionHas('success');
        $this->actingAs($verified)->post(route('verification.send'))->assertRedirect(route('play.dashboard'));

        Notification::assertSentTo($unverified, VerifyEmail::class);
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    public function test_existing_venue_owners_keep_access_without_confirming(): void
    {
        $user = User::factory()->unverified()->create();
        $business = Business::factory()->create();
        $business->users()->attach($user->id, ['role' => 'owner']);

        $this->actingAs($user)->get(route('venues.edit', $business))->assertOk();
    }
}

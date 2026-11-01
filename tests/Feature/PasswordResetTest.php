<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const NEW_PASSWORD = 'BrandNewPassword456';

    private function resetDetails(User $user, string $token, array $overrides = []): array
    {
        return $overrides + ['token' => $token, 'email' => $user->email, 'password' => self::NEW_PASSWORD, 'password_confirmation' => self::NEW_PASSWORD];
    }

    public function test_login_page_links_to_the_reset_form(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
    }

    public function test_requesting_a_link_emails_the_account_regardless_of_email_case(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'test.person@example.test']);

        $this->from(route('password.request'))->post(route('password.email'), ['email' => ' Test.Person@Example.test '])->assertRedirect(route('password.request'))->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_emails_get_the_same_response_and_no_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $known = $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        $knownMessage = session('success');
        $this->post(route('password.email'), ['email' => 'nobody@example.test'])->assertSessionHasNoErrors()->assertSessionHas('success', $knownMessage);

        $known->assertRedirect();
        Notification::assertCount(1);
    }

    public function test_a_valid_link_changes_the_password_and_can_only_be_used_once(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee($token)->assertSee($user->email);
        $this->post(route('password.update'), $this->resetDetails($user, $token))->assertRedirect(route('login'))->assertSessionHas('success');

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
        $this->assertGuest();

        $this->post(route('password.update'), $this->resetDetails($user, $token, ['password' => 'AnotherPassword789', 'password_confirmation' => 'AnotherPassword789']))->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $user->fresh()->password));
    }

    public function test_bad_tokens_and_unknown_emails_get_the_same_error_and_change_nothing(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $token = Password::createToken($user);
        $message = 'This reset link is invalid or has expired. Request a new one.';

        $this->post(route('password.update'), $this->resetDetails($user, 'wrong-token'))->assertSessionHasErrors(['email' => $message]);
        $this->post(route('password.update'), $this->resetDetails($user, $token, ['email' => 'nobody@example.test']))->assertSessionHasErrors(['email' => $message]);

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_expired_links_are_rejected(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $token = Password::createToken($user);

        $this->travel(61)->minutes();

        $this->post(route('password.update'), $this->resetDetails($user, $token))->assertSessionHasErrors('email');
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_new_passwords_must_meet_the_sign_up_rules(): void
    {
        $user = User::factory()->create();
        $original = $user->password;
        $token = Password::createToken($user);

        $this->post(route('password.update'), $this->resetDetails($user, $token, ['password' => 'short1', 'password_confirmation' => 'short1']))->assertSessionHasErrors('password');
        $this->post(route('password.update'), $this->resetDetails($user, $token, ['password_confirmation' => 'DifferentPassword789']))->assertSessionHasErrors('password');

        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_signed_in_users_are_sent_away_from_the_reset_pages(): void
    {
        $this->actingAs(User::factory()->create())->get(route('password.request'))->assertRedirect();
    }
}

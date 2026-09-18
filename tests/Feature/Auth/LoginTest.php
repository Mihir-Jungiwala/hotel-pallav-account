<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\PasswordPolicy;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function attempt(string $username, string $password = UserFactory::PASSWORD)
    {
        return $this->post(route('login.attempt'), compact('username', 'password'));
    }

    public function test_user_signs_in_with_username(): void
    {
        // Without an email there is nowhere to send a code, so the password is enough
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => null]);

        $this->attempt('ravi')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'activity_type' => 'Login']);
    }

    public function test_username_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'ravi', 'email' => null]);

        $this->attempt('RaVi');

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_account_that_asked_for_the_code_is_sent_one_first(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        User::factory()->editor()->create([
            'username' => 'ravi', 'email' => 'ravi@example.com', 'two_factor_enabled' => true,
        ]);

        $this->attempt('ravi')->assertRedirect(route('login.verify'));

        $this->assertGuest();
    }

    public function test_email_address_cannot_be_used_to_sign_in(): void
    {
        User::factory()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);

        $this->attempt('ravi@example.com')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_wrong_password_gives_the_same_message_as_unknown_user(): void
    {
        User::factory()->create(['username' => 'ravi']);

        $this->attempt('ravi', 'Nope@12345')->assertSessionHasErrors(['username' => 'Incorrect username or password.']);
        $this->attempt('nobody')->assertSessionHasErrors(['username' => 'Incorrect username or password.']);

        $this->assertGuest();
    }

    public function test_account_locks_after_repeated_failures_even_for_the_right_password(): void
    {
        $user = User::factory()->create(['username' => 'ravi']);

        for ($i = 0; $i < PasswordPolicy::MAX_ATTEMPTS; $i++) {
            $this->attempt('ravi', 'Wrong@12345');
        }

        $this->assertTrue($user->fresh()->isLocked());

        $this->attempt('ravi')->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_inactive_account_cannot_sign_in(): void
    {
        User::factory()->inactive()->create(['username' => 'ravi']);

        $this->attempt('ravi')->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_deactivating_a_signed_in_user_signs_them_out_on_next_request(): void
    {
        $user = User::factory()->editor()->create();
        $this->actingAs($user);

        $user->update(['is_active' => false]);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_temporary_password_must_be_changed_before_using_the_app(): void
    {
        $user = User::factory()->editor()->create(['must_change_password' => true]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.show', ['tab' => 'password']));

        $this->put(route('profile.password'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'Brand@New99',
            'password_confirmation' => 'Brand@New99',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->must_change_password);
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_weak_passwords_are_rejected(): void
    {
        $user = User::factory()->editor()->create();

        $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => UserFactory::PASSWORD,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('users.index'))->assertRedirect(route('login'));
    }
}

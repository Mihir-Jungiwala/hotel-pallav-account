<?php

namespace Tests\Feature\Auth;

use App\Mail\NewAccountMail;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Nobody chooses somebody else's password. A new account is emailed one that
 * works once, and the account holder replaces it before they can reach
 * anything - then signs in again with the one only they know.
 */
class NewAccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function createAccount(array $overrides = []): array
    {
        Mail::fake();

        $this->actingAs($this->admin());

        $this->post(route('users.store'), array_merge([
            'name' => 'Nita Shah',
            'username' => 'nita',
            'email' => 'nita@example.com',
            'role' => 'Editor',
        ], $overrides));

        $user = User::where('username', 'nita')->first();
        $password = null;

        Mail::assertSent(NewAccountMail::class, function (NewAccountMail $mail) use (&$password) {
            $password = $mail->temporaryPassword;

            return true;
        });

        return [$user, $password];
    }

    public function test_a_new_account_is_emailed_a_generated_password_and_must_replace_it(): void
    {
        [$user, $password] = $this->createAccount();

        $this->assertNotNull($user);
        $this->assertTrue($user->must_change_password);

        // Generated, strong, and never stored where it can be read back
        $this->assertNotNull($password);
        $this->assertMatchesRegularExpression(PasswordPolicy::REGEX, $password);
        $this->assertTrue(Hash::check($password, $user->password));

        // e(), because a password holding & or " is escaped in the HTML it renders to
        Mail::assertSent(NewAccountMail::class, fn (NewAccountMail $mail) => $mail->hasTo('nita@example.com')
            && str_contains($mail->render(), e($password))
            && str_contains($mail->render(), 'nita'));
    }

    public function test_an_account_cannot_be_created_without_somewhere_to_send_the_password(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());

        $this->post(route('users.store'), [
            'name' => 'No Email', 'username' => 'noemail', 'role' => 'Editor',
        ])->assertSessionHasErrors('email');

        $this->assertNull(User::where('username', 'noemail')->first());
        Mail::assertNothingSent();
    }

    public function test_the_temporary_password_gets_no_further_than_choosing_a_real_one(): void
    {
        [$user, $password] = $this->createAccount();
        $this->app['auth']->logout();

        // Signing in with it opens no session at all
        $this->post(route('login.attempt'), ['username' => 'nita', 'password' => $password])
            ->assertRedirect(route('password.first'));
        $this->assertGuest();

        $this->get(route('password.first'))->assertOk()->assertSee('Choose your password');
    }

    public function test_the_new_password_is_set_by_proving_the_emailed_one(): void
    {
        [$user, $password] = $this->createAccount();
        $this->app['auth']->logout();

        $this->post(route('login.attempt'), ['username' => 'nita', 'password' => $password]);

        // The wrong temporary password is refused
        $this->post(route('password.first.update'), [
            'current_password' => 'Wrong#12345',
            'password' => 'Chosen#12345', 'password_confirmation' => 'Chosen#12345',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue($user->fresh()->must_change_password);

        // The same password again is refused: it has to actually change
        $this->post(route('password.first.update'), [
            'current_password' => $password,
            'password' => $password, 'password_confirmation' => $password,
        ])->assertSessionHasErrors('password');

        // And a weak one is refused
        $this->post(route('password.first.update'), [
            'current_password' => $password,
            'password' => 'simple', 'password_confirmation' => 'simple',
        ])->assertSessionHasErrors('password');

        // The real one works, and sends them back to sign in with it
        $this->post(route('password.first.update'), [
            'current_password' => $password,
            'password' => 'Chosen#12345', 'password_confirmation' => 'Chosen#12345',
        ])->assertRedirect(route('login'))->assertSessionHas('success');

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Chosen#12345', $user->password));
        $this->assertGuest();
    }

    public function test_the_emailed_password_stops_working_once_it_is_replaced(): void
    {
        [$user, $password] = $this->createAccount();
        $this->app['auth']->logout();

        $this->post(route('login.attempt'), ['username' => 'nita', 'password' => $password]);
        $this->post(route('password.first.update'), [
            'current_password' => $password,
            'password' => 'Chosen#12345', 'password_confirmation' => 'Chosen#12345',
        ]);

        $this->post(route('login.attempt'), ['username' => 'nita', 'password' => $password])
            ->assertSessionHasErrors();
        $this->assertGuest();
    }

    public function test_the_screen_cannot_be_reached_without_signing_in_with_the_emailed_password(): void
    {
        $this->createAccount();
        $this->app['auth']->logout();
        $this->flushSession();

        $this->get(route('password.first'))->assertRedirect(route('login'));
        $this->post(route('password.first.update'), [
            'current_password' => 'anything', 'password' => 'Chosen#12345', 'password_confirmation' => 'Chosen#12345',
        ])->assertRedirect(route('login'));
    }

    public function test_resetting_a_password_emails_a_new_one_and_forces_the_same_change(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $user = User::factory()->editor()->create(['email' => 'editor@example.com', 'must_change_password' => false]);

        $this->actingAs($admin)
            ->put(route('users.reset-password', $user))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue($user->must_change_password);

        Mail::assertSent(NewAccountMail::class, function (NewAccountMail $mail) use ($user) {
            return $mail->hasTo('editor@example.com')
                && $mail->isReset
                && Hash::check($mail->temporaryPassword, $user->password);
        });
    }

    public function test_a_password_cannot_be_reset_for_someone_with_no_email(): void
    {
        Mail::fake();
        $user = User::factory()->editor()->create(['email' => null]);

        $this->actingAs($this->admin())
            ->put(route('users.reset-password', $user))
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_every_generated_password_meets_the_policy_and_no_two_are_alike(): void
    {
        $seen = [];

        for ($i = 0; $i < 50; $i++) {
            $password = PasswordPolicy::generate();
            $this->assertMatchesRegularExpression(PasswordPolicy::REGEX, $password, $password.' fails the policy');
            $seen[] = $password;
        }

        $this->assertCount(50, array_unique($seen));
    }
}

<?php

namespace Tests\Feature\Auth;

use App\Mail\OneTimeCodeMail;
use App\Models\User;
use App\Services\Auth\OneTimeCode;
use App\Support\PasswordPolicy;
use App\Support\SuperAdminIndex;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /** An account that has asked for the emailed code. */
    private function withCode(array $extra = []): User
    {
        return User::factory()->editor()->create(array_merge([
            'username' => 'ravi',
            'email' => 'ravi@example.com',
            'two_factor_enabled' => true,
        ], $extra));
    }

    private function step1(string $username = 'ravi', string $password = UserFactory::PASSWORD)
    {
        return $this->post(route('login.attempt'), compact('username', 'password'));
    }

    private function codeFor(User $user): string
    {
        $code = null;

        Mail::assertSent(OneTimeCodeMail::class, function (OneTimeCodeMail $mail) use ($user, &$code) {
            if ($mail->user->is($user)) {
                $code = $mail->code;

                return true;
            }

            return false;
        });

        return $code;
    }

    /* ------------------------------------------------- the code is opt in */

    public function test_the_code_is_off_until_someone_turns_it_on(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);

        $this->assertFalse($user->two_factor_enabled);

        $this->step1()->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_a_person_can_turn_the_code_on_and_off_for_themselves(): void
    {
        $user = User::factory()->editor()->create(['email' => 'ravi@example.com']);

        $this->actingAs($user)->post(route('profile.two-factor'))->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->two_factor_enabled);

        $this->post(route('profile.two-factor'));
        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_the_code_cannot_be_turned_on_without_an_email(): void
    {
        $user = User::factory()->editor()->create(['email' => null]);

        $this->actingAs($user)->post(route('profile.two-factor'))->assertSessionHas('error');

        $this->assertFalse($user->fresh()->two_factor_enabled);
    }

    public function test_an_admin_can_turn_it_on_for_someone_else(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create(['email' => 'ravi@example.com']);

        $this->actingAs($admin)->post(route('users.two-factor', $editor))->assertSessionHasNoErrors();

        $this->assertTrue($editor->fresh()->two_factor_enabled);
    }

    /* ------------------------------------------------------- the two steps */

    public function test_with_the_code_on_the_password_alone_is_not_enough(): void
    {
        $user = $this->withCode();

        $this->step1()->assertRedirect(route('login.verify'));

        $this->assertGuest();
        Mail::assertSent(OneTimeCodeMail::class);
    }

    public function test_the_code_completes_the_sign_in(): void
    {
        $user = $this->withCode();
        $this->step1();

        $this->post(route('login.verify.check'), ['code' => $this->codeFor($user)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        $user->refresh();
        $this->assertNull($user->otp_hash);
        $this->assertNotNull($user->current_session_id);
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = $this->withCode();
        $this->step1();
        $code = $this->codeFor($user);

        $this->travel(OneTimeCode::VALID_MINUTES + 1)->minutes();

        $this->post(route('login.verify.check'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /* ------------------------------------------- five tries block, no timer */

    public function test_five_wrong_passwords_block_the_account(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);

        for ($i = 0; $i < PasswordPolicy::MAX_ATTEMPTS - 1; $i++) {
            $this->step1('ravi', 'Wrong@12345');
        }

        $this->assertFalse($user->fresh()->isBlocked());

        $this->step1('ravi', 'Wrong@12345');

        $user->refresh();
        $this->assertTrue($user->isBlocked());
        $this->assertSame('Five wrong passwords', $user->blocked_reason);

        // Even the right password is refused while blocked
        $this->step1()->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_five_wrong_codes_block_the_account(): void
    {
        $user = $this->withCode();
        $this->step1();

        for ($i = 0; $i < PasswordPolicy::MAX_ATTEMPTS; $i++) {
            $this->post(route('login.verify.check'), ['code' => '000000']);
        }

        $this->assertTrue($user->fresh()->isBlocked());
        $this->assertGuest();
    }

    public function test_a_block_has_no_timer_and_waiting_does_not_clear_it(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);
        app(OneTimeCode::class)->block($user, 'test');

        $this->travel(2)->days();

        $this->assertTrue($user->fresh()->isBlocked());
        $this->step1()->assertSessionHasErrors('username');
    }

    public function test_an_administrator_can_unblock_an_account(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->editor()->create(['username' => 'ravi']);
        app(OneTimeCode::class)->block($user, 'test');

        $this->actingAs($admin)->post(route('users.unlock', $user))->assertSessionHasNoErrors();

        $this->assertFalse($user->fresh()->isBlocked());
    }

    public function test_resetting_the_password_by_email_unblocks_the_account(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);
        app(OneTimeCode::class)->block($user, 'test');

        $this->post(route('password.email'), ['username' => 'ravi'])->assertRedirect(route('password.code'));

        $this->post(route('password.update'), [
            'code' => $this->codeFor($user->fresh()),
            'password' => 'Brand@New99',
            'password_confirmation' => 'Brand@New99',
        ])->assertRedirect(route('login'));

        $user->refresh();
        $this->assertFalse($user->isBlocked());
        $this->assertFalse($user->must_change_password);
    }

    /* ------------------------------------------- the email wait, not a block */

    public function test_a_sixth_code_request_starts_a_wait_instead_of_blocking(): void
    {
        $user = $this->withCode();
        $codes = app(OneTimeCode::class);

        for ($i = 0; $i < OneTimeCode::MAX_SENDS; $i++) {
            $this->travel(2)->minutes();
            $this->assertTrue($codes->send($user->fresh(), OneTimeCode::LOGIN), 'send '.$i);
        }

        $this->travel(2)->minutes();
        $this->assertFalse($codes->send($user->fresh(), OneTimeCode::LOGIN));

        $user->refresh();
        $this->assertTrue($user->isWaitingForCode());
        $this->assertFalse($user->isBlocked(), 'asking for emails must not block the account');
    }

    public function test_each_wait_is_longer_than_the_last_and_stops_at_a_day(): void
    {
        $user = $this->withCode();
        $codes = app(OneTimeCode::class);

        foreach (PasswordPolicy::LOCK_STEPS as $index => $minutes) {
            $user->forceFill(['otp_cooldown_level' => $index])->save();

            $this->assertSame($minutes, $codes->startWait($user->fresh()));
        }

        $user->forceFill(['otp_cooldown_level' => 30])->save();
        $this->assertSame(1440, $codes->startWait($user->fresh()));
    }

    public function test_the_wait_passes_and_the_count_starts_again(): void
    {
        $user = $this->withCode();
        $codes = app(OneTimeCode::class);
        $codes->startWait($user);

        $this->assertFalse($codes->send($user->fresh(), OneTimeCode::LOGIN));

        // Once the wait is over, codes flow again
        $this->travel(PasswordPolicy::LOCK_STEPS[0] + 1)->minutes();

        $this->assertTrue($codes->send($user->fresh(), OneTimeCode::LOGIN));
        $this->assertSame(OneTimeCode::MAX_SENDS - 1, $codes->sendsLeft($user->fresh()));
    }

    /* --------------------------------------------------------- one device */

    public function test_only_one_device_may_hold_a_session(): void
    {
        $user = $this->withCode();
        $this->step1();
        $this->post(route('login.verify.check'), ['code' => $this->codeFor($user)]);
        $this->get(route('dashboard'))->assertOk();

        $user->forceFill(['current_session_id' => 'another-device'])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signing_out_releases_the_slot(): void
    {
        $user = $this->withCode();
        $this->step1();
        $this->post(route('login.verify.check'), ['code' => $this->codeFor($user)]);

        $this->post(route('logout'));

        $this->assertNull($user->fresh()->current_session_id);
    }

    public function test_remember_me_is_gone(): void
    {
        $this->get(route('login'))->assertDontSee('name="remember"', false);
    }

    /* ------------------------------------------------------- the DB rule */

    public function test_the_single_superadmin_index_still_carries_its_condition(): void
    {
        // Adding a column to users on SQLite rebuilds the table and can drop
        // the WHERE clause, turning the rule into "one of each role"
        $this->assertTrue(SuperAdminIndex::isPartial(), 'run SuperAdminIndex::ensure() in a migration');

        User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->assertSame(2, User::where('role', 'Admin')->count());
    }
}

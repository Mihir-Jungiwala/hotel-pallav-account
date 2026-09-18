<?php

namespace Tests\Feature\Auth;

use App\Mail\OneTimeCodeMail;
use App\Models\User;
use App\Services\Auth\OneTimeCode;
use App\Support\PasswordPolicy;
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

    private function step1(string $username, string $password = UserFactory::PASSWORD)
    {
        return $this->post(route('login.attempt'), compact('username', 'password'));
    }

    /** Pulls the code out of the mail that was queued for sending. */
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

    public function test_the_password_alone_does_not_sign_you_in(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);

        $this->step1('ravi')->assertRedirect(route('login.verify'));

        $this->assertGuest();
        Mail::assertSent(OneTimeCodeMail::class);
        $this->assertNotNull($user->fresh()->otp_hash);
    }

    public function test_the_code_completes_the_sign_in(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);
        $this->step1('ravi');

        $this->post(route('login.verify.check'), ['code' => $this->codeFor($user)])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);

        // The code is spent, and the session is recorded against the account
        $user->refresh();
        $this->assertNull($user->otp_hash);
        $this->assertNotNull($user->current_session_id);
        $this->assertDatabaseHas('user_audit_logs', ['action' => 'login.verified', 'target_user_id' => $user->id]);
    }

    public function test_a_wrong_code_is_counted_and_the_fifth_locks_the_account(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);
        $this->step1('ravi');

        for ($i = 0; $i < PasswordPolicy::MAX_ATTEMPTS - 1; $i++) {
            $this->post(route('login.verify.check'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->assertFalse($user->fresh()->isLocked());

        $this->post(route('login.verify.check'), ['code' => '000000'])->assertSessionHasErrors('code');

        $user->refresh();
        $this->assertTrue($user->isLocked());
        $this->assertGuest();
    }

    public function test_an_expired_code_is_refused(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);
        $this->step1('ravi');
        $code = $this->codeFor($user);

        $this->travel(OneTimeCode::VALID_MINUTES + 1)->minutes();

        $this->post(route('login.verify.check'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_locks_grow_each_time_up_to_a_day(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi']);
        $codes = app(OneTimeCode::class);

        $expected = PasswordPolicy::LOCK_STEPS;

        foreach ($expected as $index => $minutes) {
            $user->forceFill(['lock_level' => $index])->save();

            $this->assertSame($minutes, $codes->lock($user->fresh(), 'test'));
        }

        // It never goes past a day
        $user->forceFill(['lock_level' => 20])->save();
        $this->assertSame(1440, $codes->lock($user->fresh(), 'test'));
    }

    public function test_an_account_without_an_email_signs_in_on_the_password_alone(): void
    {
        $user = User::factory()->editor()->create(['username' => 'noemail', 'email' => null]);

        $this->step1('noemail')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_only_one_device_may_hold_a_session(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);

        // First device signs in
        $this->step1('ravi');
        $this->post(route('login.verify.check'), ['code' => $this->codeFor($user)]);
        $this->get(route('dashboard'))->assertOk();

        // A second device takes the slot
        $user->forceFill(['current_session_id' => 'another-device-session'])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_signing_out_releases_the_slot(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);
        $this->step1('ravi');
        $this->post(route('login.verify.check'), ['code' => $this->codeFor($user)]);

        $this->post(route('logout'));

        $this->assertNull($user->fresh()->current_session_id);
    }

    public function test_codes_cannot_be_requested_forever(): void
    {
        $user = User::factory()->editor()->create(['username' => 'ravi', 'email' => 'ravi@example.com']);
        $codes = app(OneTimeCode::class);

        for ($i = 0; $i < OneTimeCode::MAX_SENDS; $i++) {
            $this->travel(2)->minutes();
            $this->assertTrue($codes->send($user->fresh(), OneTimeCode::LOGIN));
        }

        $this->travel(2)->minutes();
        $this->assertFalse($codes->send($user->fresh(), OneTimeCode::LOGIN));
        $this->assertTrue($user->fresh()->isLocked());
    }

    public function test_remember_me_is_gone(): void
    {
        $this->get(route('login'))->assertDontSee('name="remember"', false);
    }
}

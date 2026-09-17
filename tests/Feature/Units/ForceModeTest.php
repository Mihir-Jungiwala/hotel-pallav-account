<?php

namespace Tests\Feature\Units;

use App\Models\CompanyProfile;
use App\Models\HotelCashDeposit;
use App\Models\User;
use App\Support\ForceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForceModeTest extends TestCase
{
    use RefreshDatabase;

    private function arm(): void
    {
        $this->post(route('force-mode.toggle'))->assertSessionHasNoErrors();
        $this->assertTrue(ForceMode::enabled());
    }

    public function test_only_the_superadmin_can_arm_it(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('force-mode.toggle'))
            ->assertForbidden();

        $this->assertFalse(ForceMode::enabled());
    }

    public function test_arming_and_disarming_is_audited(): void
    {
        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super);

        $this->arm();
        $this->post(route('force-mode.toggle'));

        $this->assertFalse(ForceMode::enabled());
        $this->assertDatabaseHas('user_audit_logs', ['action' => 'force.enabled', 'actor_id' => $super->id]);
        $this->assertDatabaseHas('user_audit_logs', ['action' => 'force.disabled', 'actor_id' => $super->id]);
    }

    public function test_required_fields_are_enforced_until_force_mode_is_armed(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        // A deposit normally needs a date, depositor and amount
        $this->post(route('revenue.hotel.store'), ['depositor' => ''])->assertSessionHasErrors(['date', 'depositor', 'amount']);

        $this->arm();

        $this->post(route('revenue.hotel.store'), ['depositor' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('hotel_cash_deposits', 1);
    }

    public function test_upload_and_type_rules_still_apply_under_force_mode(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->arm();

        // 'amount' is a decimal column, so a word is still refused
        $this->post(route('revenue.hotel.store'), [
            'date' => now()->toDateString(), 'depositor' => 'Desk', 'amount' => 'lots',
        ])->assertSessionHasErrors('amount');
    }

    public function test_relax_keeps_types_and_uploads_but_drops_refusals(): void
    {
        $relaxed = ForceMode::relax([
            'name' => ['required', 'string', 'max:100', 'unique:users,username'],
            'amount' => ['required', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $this->assertSame(['string', 'nullable'], $relaxed['name']);
        $this->assertSame(['numeric', 'nullable'], $relaxed['amount']);
        $this->assertSame(['nullable', 'image', 'max:2048'], $relaxed['logo']);
    }

    public function test_a_record_lock_is_stepped_past_and_written_to_the_audit_log(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        // Off: the lock holds and nothing is audited
        $this->assertTrue(ForceMode::locked(true, 'Salary already processed'));
        $this->assertDatabaseMissing('user_audit_logs', ['action' => 'force.override']);

        $this->arm();

        $this->assertFalse(ForceMode::locked(true, 'Salary already processed'));
        $this->assertDatabaseHas('user_audit_logs', ['action' => 'force.override']);
    }

    public function test_an_unlocked_action_is_never_audited_as_an_override(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->arm();

        $this->assertFalse(ForceMode::locked(false, 'Nothing was blocking'));
        $this->assertDatabaseMissing('user_audit_logs', ['action' => 'force.override']);
    }

    public function test_locks_still_hold_for_everyone_else(): void
    {
        $editor = User::factory()->editor()->create();
        $company = CompanyProfile::create(['name' => 'Locked Co']);

        $this->actingAs($editor)->delete(route('company.destroy', $company))->assertSessionHas('error');

        $this->assertModelExists($company);
        $this->assertDatabaseMissing('user_audit_logs', ['action' => 'force.override']);
    }
}

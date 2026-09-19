<?php

namespace Tests\Feature\Ledger;

use App\Models\Employee;
use App\Models\FoodCashDeposit;
use App\Models\FoodMiscExpense;
use App\Models\HotelCashDeposit;
use App\Models\HotelCashWithdrawal;
use App\Models\HotelMiscExpense;
use App\Models\OptionSet;
use App\Models\PayrollCompany;
use App\Models\StaffAdvance;
use App\Models\User;
use App\Support\ForceMode;
use App\Support\Masters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBooksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    private function deposit(string $model, string $who, ?User $by = null): HotelCashDeposit|FoodCashDeposit
    {
        $by ??= $this->admin;

        return $model::create([
            'date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $by->id, 'full_name' => $by->name,
            'depositor' => $who, 'amount' => 100, 'amount_in_words' => 'One Hundred Rupees Only',
        ]);
    }

    private function people(): array
    {
        Masters::flush();

        return Masters::values('cash_person');
    }

    // ---- filter ---------------------------------------------------------

    public function test_revenue_can_show_all_hotel_or_food(): void
    {
        $this->deposit(HotelCashDeposit::class, 'Hotel Person Alpha');
        $this->deposit(FoodCashDeposit::class, 'Food Person Beta');

        $this->get(route('revenue.index'))->assertOk()->assertSee('Hotel Person Alpha')->assertSee('Food Person Beta');
        $this->get(route('revenue.index', ['book' => 'hotel']))->assertSee('Hotel Person Alpha')->assertDontSee('Food Person Beta');
        $this->get(route('revenue.index', ['book' => 'food']))->assertSee('Food Person Beta')->assertDontSee('Hotel Person Alpha');
        $this->get(route('revenue.index', ['book' => 'nonsense']))->assertSee('Hotel Person Alpha')->assertSee('Food Person Beta');
    }

    public function test_expense_filters_by_book_and_kind_and_advances_stay_out_of_a_single_book(): void
    {
        $company = PayrollCompany::create(['name' => 'Pallav', 'code' => 'P01', 'is_active' => true]);
        $employee = Employee::create(['payroll_company_id' => $company->id, 'employee_code' => 'E1', 'name' => 'Cook Gamma', 'salary' => 1, 'is_active' => true]);
        $stamp = ['date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->admin->id, 'full_name' => 'A', 'amount' => 5];

        HotelMiscExpense::create($stamp + ['expense_name' => 'Hotel Bulbs']);
        FoodMiscExpense::create($stamp + ['expense_name' => 'Food Gas']);
        HotelCashWithdrawal::create($stamp + ['withdrawer' => 'Withdrawer Delta']);
        StaffAdvance::create($stamp + ['employee_id' => $employee->id, 'year_month' => now()->format('Y-m')]);

        // The staff picker in the pop-up always lists the employee, so look for the advance's own row
        $advanceRow = 'cb-chip kind">Staff advance';

        $this->get(route('expense.index'))->assertSee('Hotel Bulbs')->assertSee('Food Gas')->assertSee('Withdrawer Delta')->assertSee($advanceRow, false);
        $this->get(route('expense.index', ['book' => 'food']))->assertSee('Food Gas')->assertDontSee('Hotel Bulbs')->assertDontSee($advanceRow, false);
        $this->get(route('expense.index', ['kind' => 'misc']))->assertSee('Hotel Bulbs')->assertDontSee('Withdrawer Delta')->assertDontSee($advanceRow, false);
        $this->get(route('expense.index', ['kind' => 'advance']))->assertSee($advanceRow, false)->assertDontSee('Hotel Bulbs');
    }

    // ---- names from Master Data ----------------------------------------

    public function test_a_new_depositor_is_added_to_master_data_once(): void
    {
        $this->assertNotNull(OptionSet::where('key', 'cash_person')->first());

        $row = ['date' => now()->toDateString(), 'time' => '09:00', 'amount' => 50];

        $this->post(route('revenue.hotel.store'), $row + ['depositor' => '__other__', 'depositor_new' => 'Meera Shah'])->assertSessionHasNoErrors();
        $this->post(route('revenue.food.store'), $row + ['depositor' => '__other__', 'depositor_new' => 'meera shah'])->assertSessionHasNoErrors();

        $this->assertSame('Meera Shah', HotelCashDeposit::sole()->depositor);
        // The second spelling matches the listed name instead of adding a twin
        $this->assertSame('Meera Shah', FoodCashDeposit::sole()->depositor);
        $this->assertSame(['Meera Shah'], $this->people());
    }

    public function test_choosing_other_without_typing_a_name_is_refused(): void
    {
        $this->post(route('revenue.hotel.store'), ['date' => now()->toDateString(), 'time' => '09:00', 'amount' => 50, 'depositor' => '__other__'])
            ->assertSessionHasErrors('depositor');

        $this->assertSame(0, HotelCashDeposit::count());
    }

    public function test_a_withdrawer_joins_the_same_list_and_the_form_offers_it(): void
    {
        $this->post(route('expense.food-withdrawal.store'), [
            'date' => now()->toDateString(), 'time' => '09:00', 'amount' => 75, 'withdrawer' => '__other__', 'withdrawer_new' => 'Ravi Patel',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Ravi Patel'], $this->people());
        $this->get(route('revenue.index'))->assertSee('<option value="Ravi Patel">Ravi Patel</option>', false);
        $this->get(route('expense.index'))->assertSee('<option value="Ravi Patel">Ravi Patel</option>', false);
    }

    public function test_names_already_on_past_entries_are_a_hidden_person_again_when_reused(): void
    {
        $this->post(route('revenue.hotel.store'), ['date' => now()->toDateString(), 'time' => '09:00', 'amount' => 5, 'depositor' => 'Old Hand']);
        OptionSet::where('key', 'cash_person')->first()->items()->update(['is_active' => false]);
        $this->assertSame([], $this->people());

        $this->post(route('revenue.hotel.store'), ['date' => now()->toDateString(), 'time' => '09:00', 'amount' => 5, 'depositor' => 'Old Hand']);

        $this->assertSame(['Old Hand'], $this->people());
        $this->assertSame(1, OptionSet::where('key', 'cash_person')->first()->items()->count());
    }

    // ---- validation ------------------------------------------------------

    public function test_amounts_must_be_positive_and_a_month_must_look_like_one(): void
    {
        $row = ['date' => now()->toDateString(), 'time' => '09:00', 'depositor' => 'Someone'];

        $this->post(route('revenue.hotel.store'), $row + ['amount' => 0])->assertSessionHasErrors('amount');
        $this->post(route('revenue.hotel.store'), $row + ['amount' => -5])->assertSessionHasErrors('amount');
        $this->post(route('expense.staff-advance.store'), ['date' => now()->toDateString(), 'time' => '09:00', 'amount' => 5, 'employee_id' => 999, 'year_month' => '2026-13'])
            ->assertSessionHasErrors(['employee_id', 'year_month']);
    }

    public function test_a_refused_save_reopens_the_pop_up_with_what_was_typed(): void
    {
        $this->from(route('revenue.index'))->post(route('revenue.hotel.store'), [
            '_form' => 'cashbook', '_book' => 'hotel', 'depositor' => 'Typed Name', 'date' => now()->toDateString(), 'time' => '09:00', 'amount' => 0,
        ])->assertSessionHasErrors('amount');

        $this->get(route('revenue.index'))->assertSee('"depositor":"Typed Name"', false);
    }

    // ---- editing ---------------------------------------------------------

    public function test_a_deposit_can_be_edited_and_the_words_follow_the_amount(): void
    {
        $deposit = $this->deposit(HotelCashDeposit::class, 'Before');

        $this->put(route('revenue.hotel.update', $deposit), [
            'date' => now()->toDateString(), 'time' => '11:00', 'depositor' => 'After', 'revenue_source' => 'Banquet', 'amount' => 250,
        ])->assertSessionHasNoErrors();

        $deposit->refresh();
        $this->assertSame('After', $deposit->depositor);
        $this->assertSame('Banquet', $deposit->revenue_source);
        $this->assertSame('Two Hundred Fifty Rupees Only', $deposit->amount_in_words);
    }

    public function test_a_withdrawal_and_a_misc_expense_can_be_edited(): void
    {
        $stamp = ['date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->admin->id, 'full_name' => 'A', 'amount' => 5];
        $withdrawal = HotelCashWithdrawal::create($stamp + ['withdrawer' => 'Before']);
        $misc = HotelMiscExpense::create($stamp + ['expense_name' => 'Before']);
        $when = ['date' => now()->toDateString(), 'time' => '12:00'];

        $this->put(route('expense.hotel-withdrawal.update', $withdrawal), $when + ['withdrawer' => 'After', 'amount' => 9])->assertSessionHasNoErrors();
        $this->put(route('expense.hotel-misc.update', $misc), $when + ['expense_name' => 'After', 'expense_head' => 'Kitchen', 'amount' => 9])->assertSessionHasNoErrors();

        $this->assertSame('After', $withdrawal->fresh()->withdrawer);
        $this->assertSame('Kitchen', $misc->fresh()->expense_head);
    }

    public function test_the_receipts_open_for_every_kind_of_entry(): void
    {
        $company = PayrollCompany::create(['name' => 'Pallav', 'code' => 'P01', 'is_active' => true]);
        $employee = Employee::create(['payroll_company_id' => $company->id, 'employee_code' => 'E1', 'name' => 'Cook', 'salary' => 1, 'is_active' => true]);
        $stamp = ['date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->admin->id, 'full_name' => 'A', 'amount' => 5];

        $this->get(route('revenue.hotel.view', $this->deposit(HotelCashDeposit::class, 'X')))->assertOk();
        $this->get(route('expense.hotel-withdrawal.view', HotelCashWithdrawal::create($stamp + ['withdrawer' => 'X'])))->assertOk();
        $this->get(route('expense.food-misc.view', FoodMiscExpense::create($stamp + ['expense_name' => 'X'])))->assertOk();
        $this->get(route('expense.staff-advance.view', StaffAdvance::create($stamp + ['employee_id' => $employee->id, 'year_month' => '2026-09'])))->assertOk();
    }

    // ---- deleting: newest first, admins only -----------------------------

    public function test_entries_can_only_be_deleted_newest_first(): void
    {
        $first = $this->deposit(HotelCashDeposit::class, 'First');
        $second = $this->deposit(HotelCashDeposit::class, 'Second');

        $this->delete(route('revenue.hotel.destroy', $first))->assertSessionHas('error');
        $this->assertSame(2, HotelCashDeposit::count());

        $this->delete(route('revenue.hotel.destroy', $second))->assertSessionHas('success');
        $this->delete(route('revenue.hotel.destroy', $first))->assertSessionHas('success');
        $this->assertSame(0, HotelCashDeposit::count());
    }

    public function test_the_book_carries_on_from_the_last_number_after_the_newest_is_removed(): void
    {
        $this->deposit(HotelCashDeposit::class, 'One');
        $two = $this->deposit(HotelCashDeposit::class, 'Two');

        $this->delete(route('revenue.hotel.destroy', $two));

        $this->assertSame(2, $this->deposit(HotelCashDeposit::class, 'Again')->entry_no);
    }

    public function test_each_book_has_its_own_newest_entry(): void
    {
        $hotelOld = $this->deposit(HotelCashDeposit::class, 'Hotel old');
        $this->deposit(HotelCashDeposit::class, 'Hotel new');
        $food = $this->deposit(FoodCashDeposit::class, 'Food only');

        // Food's single entry is its newest, whatever Hotel holds
        $this->delete(route('revenue.food.destroy', $food))->assertSessionHas('success');
        $this->delete(route('revenue.hotel.destroy', $hotelOld))->assertSessionHas('error');
    }

    public function test_expense_kinds_each_delete_newest_first(): void
    {
        $stamp = ['date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->admin->id, 'full_name' => 'A', 'amount' => 5];
        $old = HotelMiscExpense::create($stamp + ['expense_name' => 'Old']);
        $new = HotelMiscExpense::create($stamp + ['expense_name' => 'New']);
        $withdrawal = HotelCashWithdrawal::create($stamp + ['withdrawer' => 'Only']);

        $this->delete(route('expense.hotel-misc.destroy', $old))->assertSessionHas('error');
        $this->delete(route('expense.hotel-misc.destroy', $new))->assertSessionHas('success');
        $this->delete(route('expense.hotel-withdrawal.destroy', $withdrawal))->assertSessionHas('success');
        $this->delete(route('expense.hotel-misc.destroy', $old))->assertSessionHas('success');
    }

    public function test_someone_below_admin_cannot_delete_even_their_own_newest_entry(): void
    {
        $editor = User::factory()->editor()->create();
        $mine = $this->deposit(HotelCashDeposit::class, 'Mine', $editor);
        $this->actingAs($editor);

        $this->delete(route('revenue.hotel.destroy', $mine))->assertSessionHas('error');
        $this->assertSame(1, HotelCashDeposit::count());

        $html = $this->get(route('revenue.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('data-confirm-title="Delete #', $html);
    }

    public function test_an_admin_cannot_delete_a_superadmins_entry_but_the_superadmin_can(): void
    {
        $super = User::factory()->superAdmin()->create();
        $theirs = $this->deposit(HotelCashDeposit::class, 'Theirs', $super);

        $this->delete(route('revenue.hotel.destroy', $theirs))->assertSessionHas('error');
        $this->assertSame(1, HotelCashDeposit::count());

        $this->actingAs($super);
        $this->delete(route('revenue.hotel.destroy', $theirs))->assertSessionHas('success');
    }

    public function test_only_the_newest_row_shows_a_delete_button(): void
    {
        $this->deposit(HotelCashDeposit::class, 'Older');
        $newest = $this->deposit(HotelCashDeposit::class, 'Newest');

        $html = $this->get(route('revenue.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-confirm-title="Delete #'));
        $this->assertStringContainsString(route('revenue.hotel.destroy', $newest), $html);
        $this->assertStringNotContainsString('Only an Admin', $html);
    }

    public function test_force_mode_lets_the_superadmin_step_past_the_order_rule(): void
    {
        $super = User::factory()->superAdmin()->create();
        $this->actingAs($super);
        $first = $this->deposit(HotelCashDeposit::class, 'First', $super);
        $this->deposit(HotelCashDeposit::class, 'Second', $super);

        $this->delete(route('revenue.hotel.destroy', $first))->assertSessionHas('error');

        $this->post(route('force-mode.toggle'));
        $this->assertTrue(ForceMode::enabled());
        $this->delete(route('revenue.hotel.destroy', $first))->assertSessionHas('success');
        $this->assertDatabaseHas('user_audit_logs', ['action' => 'force.override']);
    }

    // ---- paging ----------------------------------------------------------

    public function test_the_list_pages_across_both_books(): void
    {
        foreach (range(1, 8) as $i) {
            $this->deposit(HotelCashDeposit::class, 'Hotel '.$i);
            $this->deposit(FoodCashDeposit::class, 'Food '.$i);
        }

        $this->get(route('revenue.index'))->assertOk()->assertSee('Showing 1-10 of 16');
        $this->get(route('revenue.index', ['page' => 2]))->assertOk()->assertSee('Showing 11-16 of 16');
        $this->get(route('revenue.index', ['book' => 'food', 'per' => 25]))->assertSee('Showing 1-8 of 8');
    }

    // ---- search ----------------------------------------------------------

    public function test_revenue_search_covers_every_page_and_understands_payroll_style_terms(): void
    {
        foreach (range(1, 12) as $i) {
            $this->deposit(HotelCashDeposit::class, 'Filler '.$i);
        }
        HotelCashDeposit::create([
            'date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->admin->id, 'full_name' => 'A',
            'depositor' => 'Needle Person', 'revenue_source' => 'Banquet', 'amount' => 24000, 'amount_in_words' => 'x',
        ]);
        $this->deposit(FoodCashDeposit::class, 'Food Haystack');

        // The match is on a later page of the unfiltered list, yet search finds it
        $this->get(route('revenue.index', ['q' => 'needle']))->assertSee('Needle Person')->assertDontSee('Filler 1<')->assertSee('Showing 1-1 of 1');
        $this->get(route('revenue.index', ['q' => 'person banquet']))->assertSee('Needle Person');
        $this->get(route('revenue.index', ['q' => 'person nothing']))->assertDontSee('Needle Person');
        $this->get(route('revenue.index', ['q' => '"needle person"']))->assertSee('Needle Person');
        $this->get(route('revenue.index', ['q' => '24,000']))->assertSee('Needle Person');
        $this->get(route('revenue.index', ['q' => '>20000']))->assertSee('Needle Person')->assertSee('Showing 1-1 of 1');
        $this->get(route('revenue.index', ['q' => '<50']))->assertSee('No deposits match');
        $this->get(route('revenue.index', ['q' => '100-200']))->assertSee('Filler 3')->assertDontSee('Needle Person');
        $this->get(route('revenue.index', ['q' => 'pallav food']))->assertSee('Food Haystack')->assertDontSee('Needle Person');
        $this->get(route('revenue.index', ['q' => 'filler -filler']))->assertSee('No deposits match');
        $this->get(route('revenue.index', ['q' => now()->format('d M Y'), 'book' => 'food']))->assertSee('Food Haystack')->assertDontSee('Needle Person');
    }

    public function test_the_search_box_keeps_the_filters_and_can_be_cleared(): void
    {
        $html = $this->get(route('expense.index', ['book' => 'hotel', 'kind' => 'misc', 'q' => 'bulbs']))->assertOk()->getContent();

        $this->assertStringContainsString('name="book" value="hotel"', $html);
        $this->assertStringContainsString('name="kind" value="misc"', $html);
        $this->assertStringContainsString('value="bulbs"', $html);
        $this->assertStringContainsString('search-clear', $html);
    }

    public function test_expense_search_finds_staff_by_name_and_by_kind(): void
    {
        $company = PayrollCompany::create(['name' => 'Pallav', 'code' => 'P01', 'is_active' => true]);
        $employee = Employee::create(['payroll_company_id' => $company->id, 'employee_code' => 'E1', 'name' => 'Zed Cook', 'salary' => 1, 'is_active' => true]);
        $stamp = ['date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->admin->id, 'full_name' => 'A', 'amount' => 5];
        StaffAdvance::create($stamp + ['employee_id' => $employee->id, 'year_month' => now()->format('Y-m')]);
        HotelMiscExpense::create($stamp + ['expense_name' => 'Hotel Bulbs']);

        $row = 'cb-chip kind">';
        $this->get(route('expense.index', ['q' => 'zed']))->assertSee($row.'Staff advance', false)->assertDontSee('Hotel Bulbs');
        $this->get(route('expense.index', ['q' => 'misc']))->assertSee('Hotel Bulbs')->assertDontSee($row.'Staff advance', false);
    }
}

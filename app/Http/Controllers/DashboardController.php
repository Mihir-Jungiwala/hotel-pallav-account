<?php

namespace App\Http\Controllers;

use App\Models\BillMasterAdvance;
use App\Models\BillMasterBill;
use App\Models\CompanyProfile;
use App\Models\Employee;
use App\Models\FoodCashDeposit;
use App\Models\FoodCashWithdrawal;
use App\Models\FoodMiscExpense;
use App\Models\HotelCashDeposit;
use App\Models\HotelCashWithdrawal;
use App\Models\HotelMiscExpense;
use App\Models\SalaryProcessing;
use App\Models\ShiftHandover;
use App\Models\StaffAdvance;
use App\Support\UnitContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The day at a glance: what came in, what went out, what is still owed and what
 * somebody still has to do. Everything follows the business chosen in the
 * sidebar.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $monthStart = $today->copy()->startOfMonth();

        $showHotel = UnitContext::shows('hotel');
        $showFood = UnitContext::shows('food');

        return view('dashboard.index', [
            'showHotel' => $showHotel,
            'showFood' => $showFood,
            'bothUnits' => $showHotel && $showFood,
            'unitLabel' => UnitContext::label(),
            'today' => $today,
            'monthLabel' => $today->format('F Y'),

            'todayTotals' => [
                'hotel' => $this->totalsFor('hotel', $today, $today),
                'food' => $this->totalsFor('food', $today, $today),
            ],
            'monthTotals' => [
                'hotel' => $this->totalsFor('hotel', $monthStart, $today),
                'food' => $this->totalsFor('food', $monthStart, $today),
            ],
            'yesterday' => [
                'hotel' => $this->totalsFor('hotel', $today->copy()->subDay(), $today->copy()->subDay()),
                'food' => $this->totalsFor('food', $today->copy()->subDay(), $today->copy()->subDay()),
            ],

            'trend' => $this->trend($today, $showHotel, $showFood),
            'receivables' => $this->receivables($showHotel, $showFood),
            'expenseHeads' => $this->expenseHeads($monthStart, $today, $showHotel, $showFood),
            'attention' => $this->attention(),
            'recent' => $this->recentEntries(),
            'shiftHandover' => UnitContext::scope(ShiftHandover::with('user'))->latest('date')->latest('time')->first(),

            'staffCount' => Employee::where('is_active', true)->count(),
            'companyCount' => CompanyProfile::count(),
        ]);
    }

    /* -------------------------------------------------------------- figures */

    /** Cash in, cash out and the balance for one business over a date range. */
    private function totalsFor(string $unit, Carbon $from, Carbon $to): array
    {
        $deposits = $unit === 'hotel' ? HotelCashDeposit::class : FoodCashDeposit::class;
        $withdrawals = $unit === 'hotel' ? HotelCashWithdrawal::class : FoodCashWithdrawal::class;
        $misc = $unit === 'hotel' ? HotelMiscExpense::class : FoodMiscExpense::class;

        $unitId = UnitContext::find($unit)?->id;

        // Dates can carry a time, so the range is compared on the date part only
        $inRange = fn (string $model, string $column = 'date') => $model::whereDate($column, '>=', $from->toDateString())
            ->whereDate($column, '<=', $to->toDateString());

        $billsCash = (float) $inRange(BillMasterBill::class, 'bill_date')
            ->whereRaw("LOWER({$unit}_mode_of_payment) = ?", ['cash'])
            ->sum("total_{$unit}_amount");

        $advancesCash = (float) $inRange(BillMasterAdvance::class, 'payment_date')
            ->whereRaw("LOWER({$unit}_mode) = ?", ['cash'])
            ->sum("{$unit}_amount");

        $income = (float) $inRange($deposits)->sum('amount') + $billsCash + $advancesCash;

        $expense = (float) $inRange($withdrawals)->sum('amount')
            + (float) $inRange($misc)->sum('amount')
            + (float) $inRange(StaffAdvance::class)
                ->when($unitId, fn ($q, $id) => $q->where('business_unit_id', $id))
                ->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'balance' => $income - $expense,
            'entries' => $inRange($deposits)->count() + $inRange($withdrawals)->count() + $inRange($misc)->count(),
        ];
    }

    /** Fourteen days of income and expense, for the chart. */
    private function trend(Carbon $today, bool $showHotel, bool $showFood): array
    {
        $labels = $income = $expense = [];

        for ($i = 13; $i >= 0; $i--) {
            $day = $today->copy()->subDays($i);
            $in = $out = 0.0;

            foreach (['hotel' => $showHotel, 'food' => $showFood] as $unit => $visible) {
                if (! $visible) {
                    continue;
                }

                $totals = $this->totalsFor($unit, $day, $day);
                $in += $totals['income'];
                $out += $totals['expense'];
            }

            $labels[] = $day->format('d M');
            $income[] = round($in, 2);
            $expense[] = round($out, 2);
        }

        return compact('labels', 'income', 'expense');
    }

    /** Money still to come in from debit bills. */
    private function receivables(bool $showHotel, bool $showFood): array
    {
        $hotel = $showHotel ? (float) BillMasterBill::sum('balance_hotel_amount') : 0.0;
        $food = $showFood ? (float) BillMasterBill::sum('balance_food_amount') : 0.0;

        $open = BillMasterBill::where(function ($q) use ($showHotel, $showFood) {
            if ($showHotel) {
                $q->orWhere('balance_hotel_amount', '>', 0);
            }
            if ($showFood) {
                $q->orWhere('balance_food_amount', '>', 0);
            }
        })->count();

        return ['hotel' => $hotel, 'food' => $food, 'total' => $hotel + $food, 'bills' => $open];
    }

    /** What this month's miscellaneous spending went on. */
    private function expenseHeads(Carbon $from, Carbon $to, bool $showHotel, bool $showFood): Collection
    {
        $rows = collect();

        $books = [['hotel', $showHotel, HotelMiscExpense::class], ['food', $showFood, FoodMiscExpense::class]];

        foreach ($books as [$unit, $visible, $model]) {
            if (! $visible) {
                continue;
            }

            $query = $model::whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString());

            foreach ($query->get(['expense_head', 'amount']) as $row) {
                $rows->push(['head' => $row->expense_head ?: 'Unsorted', 'amount' => (float) $row->amount]);
            }
        }

        return $rows->groupBy('head')
            ->map(fn ($group, $head) => ['head' => $head, 'total' => (float) $group->sum('amount')])
            ->sortByDesc('total')->values()->take(6);
    }

    /** Short list of things somebody still has to do. */
    private function attention(): Collection
    {
        $items = collect();

        $unpaid = SalaryProcessing::whereIn('payment_status', ['Pending', 'Processing', 'On Hold'])->count();
        if ($unpaid) {
            $items->push([
                'icon' => 'bi-wallet2', 'tone' => 'warn',
                'text' => $unpaid.' salary '.($unpaid === 1 ? 'payment is' : 'payments are').' still open',
                'link' => route('payroll.index', ['category' => 'salary-payment']),
                'action' => 'Open salary payments',
            ]);
        }

        $debit = BillMasterBill::where('balance_hotel_amount', '>', 0)->orWhere('balance_food_amount', '>', 0)->count();
        if ($debit) {
            $items->push([
                'icon' => 'bi-receipt', 'tone' => 'danger',
                'text' => $debit.' debit '.($debit === 1 ? 'bill is' : 'bills are').' still unpaid',
                'link' => route('bill-master.debit-bills'),
                'action' => 'Chase payment',
            ]);
        }

        $handover = UnitContext::scope(ShiftHandover::query())->latest('date')->first();
        if (! $handover || ! Carbon::parse($handover->date)->isToday()) {
            $items->push([
                'icon' => 'bi-arrow-left-right', 'tone' => 'warn',
                'text' => 'No shift handover recorded today',
                'link' => route('shift-handover.index'),
                'action' => 'Record handover',
            ]);
        }

        $advances = StaffAdvance::whereDate('date', '>=', now()->subDays(30))->sum('amount');
        if ($advances > 0) {
            $items->push([
                'icon' => 'bi-cash-stack', 'tone' => 'info',
                'text' => 'Rs '.number_format((float) $advances, 2).' paid as staff advances in 30 days',
                'link' => route('expense.index'),
                'action' => 'Review advances',
            ]);
        }

        return $items;
    }

    /** The last few entries across the cash books, newest first. */
    private function recentEntries(): Collection
    {
        $rows = collect();

        $books = [
            ['hotel', HotelCashDeposit::class, 'in', 'Hotel deposit', 'depositor'],
            ['food', FoodCashDeposit::class, 'in', 'Food deposit', 'depositor'],
            ['hotel', HotelCashWithdrawal::class, 'out', 'Hotel withdrawal', 'withdrawer'],
            ['food', FoodCashWithdrawal::class, 'out', 'Food withdrawal', 'withdrawer'],
            ['hotel', HotelMiscExpense::class, 'out', 'Hotel expense', 'expense_name'],
            ['food', FoodMiscExpense::class, 'out', 'Food expense', 'expense_name'],
        ];

        foreach ($books as [$unit, $model, $direction, $label, $whoColumn]) {
            if (! UnitContext::shows($unit)) {
                continue;
            }

            foreach ($model::with('user')->latest('date')->latest('time')->limit(5)->get() as $row) {
                $rows->push([
                    'unit' => $unit,
                    'direction' => $direction,
                    'label' => $label,
                    'who' => $row->{$whoColumn},
                    'amount' => (float) $row->amount,
                    'date' => $row->date,
                    'time' => substr((string) $row->time, 0, 5),
                    'entry' => $row->entryNumber(),
                    'by' => $row->user?->displayName() ?? $row->full_name,
                ]);
            }
        }

        return $rows->sortByDesc(fn ($row) => optional($row['date'])->format('Y-m-d').' '.$row['time'])->take(8)->values();
    }
}

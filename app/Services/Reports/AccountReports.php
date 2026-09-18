<?php

namespace App\Services\Reports;

use App\Models\BillMasterAdvance;
use App\Models\BillMasterBill;
use App\Models\FoodCashDeposit;
use App\Models\FoodCashWithdrawal;
use App\Models\FoodMiscExpense;
use App\Models\HotelCashDeposit;
use App\Models\HotelCashWithdrawal;
use App\Models\HotelMiscExpense;
use App\Models\SalaryProcessing;
use App\Models\StaffAdvance;
use App\Support\UnitContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The reports the accounts desk actually asks for. Each one returns plain rows
 * and totals, so the same figures feed the screen and the PDF.
 */
class AccountReports
{
    public const TYPES = [
        'cash-book' => [
            'name' => 'Day Book',
            'hint' => 'Every rupee in and out for a date range, with a running balance.',
            'icon' => 'bi-journal-text',
        ],
        'monthly-summary' => [
            'name' => 'Monthly Summary',
            'hint' => 'Day by day income, expense and closing balance for a month.',
            'icon' => 'bi-calendar3',
        ],
        'expense-heads' => [
            'name' => 'Expense Analysis',
            'hint' => 'What the money went on, grouped by expense head.',
            'icon' => 'bi-pie-chart',
        ],
        'revenue-sources' => [
            'name' => 'Revenue by Source',
            'hint' => 'Which collection point brought the cash in.',
            'icon' => 'bi-cash-coin',
        ],
        'receivables' => [
            'name' => 'Outstanding Bills',
            'hint' => 'Debit bills still unpaid, oldest first, with days pending.',
            'icon' => 'bi-hourglass-split',
        ],
        'advance-ledger' => [
            'name' => 'Staff Advance Ledger',
            'hint' => 'Advances paid to staff and what is still to be recovered.',
            'icon' => 'bi-people',
        ],
        'bill-register' => [
            'name' => 'Bill Register',
            'hint' => 'Every bill raised in the period with hotel and food totals.',
            'icon' => 'bi-receipt',
        ],
    ];

    public function __construct(private Carbon $from, private Carbon $to) {}

    public function build(string $type): array
    {
        return match ($type) {
            'monthly-summary' => $this->monthlySummary(),
            'expense-heads' => $this->expenseHeads(),
            'revenue-sources' => $this->revenueSources(),
            'receivables' => $this->receivables(),
            'advance-ledger' => $this->advanceLedger(),
            'bill-register' => $this->billRegister(),
            default => $this->cashBook(),
        };
    }

    /* ------------------------------------------------------------- day book */

    private function cashBook(): array
    {
        $rows = $this->movements()->sortBy(fn ($row) => $row['date'].' '.$row['time'])->values();

        $balance = 0.0;
        $rows = $rows->map(function ($row) use (&$balance) {
            $balance += $row['direction'] === 'in' ? $row['amount'] : -$row['amount'];
            $row['balance'] = $balance;

            return $row;
        });

        return [
            'rows' => $rows,
            'totals' => [
                'in' => $rows->where('direction', 'in')->sum('amount'),
                'out' => $rows->where('direction', 'out')->sum('amount'),
                'closing' => $balance,
            ],
        ];
    }

    /* ------------------------------------------------------ monthly summary */

    private function monthlySummary(): array
    {
        $movements = $this->movements()->groupBy('date');
        $rows = collect();
        $balance = 0.0;

        for ($day = $this->from->copy(); $day->lte($this->to); $day->addDay()) {
            $key = $day->toDateString();
            $entries = $movements->get($key, collect());
            $in = (float) $entries->where('direction', 'in')->sum('amount');
            $out = (float) $entries->where('direction', 'out')->sum('amount');
            $balance += $in - $out;

            $rows->push([
                'date' => $key,
                'label' => $day->format('d M, D'),
                'in' => $in,
                'out' => $out,
                'net' => $in - $out,
                'balance' => $balance,
                'entries' => $entries->count(),
            ]);
        }

        return [
            'rows' => $rows,
            'totals' => [
                'in' => $rows->sum('in'),
                'out' => $rows->sum('out'),
                'closing' => $balance,
                'busiest' => $rows->sortByDesc('in')->first(),
            ],
        ];
    }

    /* -------------------------------------------------------- expense heads */

    private function expenseHeads(): array
    {
        $rows = collect();

        foreach ($this->expenseBooks() as [$unit, $model, $labelColumn, $headColumn]) {
            foreach ($this->inRange($model)->get() as $entry) {
                $rows->push([
                    'unit' => $unit,
                    'head' => $headColumn && $entry->{$headColumn} ? $entry->{$headColumn} : 'Unsorted',
                    'name' => $entry->{$labelColumn},
                    'amount' => (float) $entry->amount,
                ]);
            }
        }

        $grouped = $rows->groupBy('head')->map(fn ($group, $head) => [
            'head' => $head,
            'count' => $group->count(),
            'hotel' => (float) $group->where('unit', 'hotel')->sum('amount'),
            'food' => (float) $group->where('unit', 'food')->sum('amount'),
            'total' => (float) $group->sum('amount'),
        ])->sortByDesc('total')->values();

        return [
            'rows' => $grouped,
            'totals' => [
                'total' => $grouped->sum('total'),
                'hotel' => $grouped->sum('hotel'),
                'food' => $grouped->sum('food'),
                'heads' => $grouped->count(),
            ],
        ];
    }

    /* ------------------------------------------------------ revenue sources */

    private function revenueSources(): array
    {
        $rows = collect();

        foreach ([['hotel', HotelCashDeposit::class], ['food', FoodCashDeposit::class]] as [$unit, $model]) {
            if (! UnitContext::shows($unit)) {
                continue;
            }

            foreach ($this->inRange($model)->get() as $entry) {
                $rows->push([
                    'unit' => $unit,
                    'source' => $entry->revenue_source ?: ($entry->depositor ?: 'Unsorted'),
                    'amount' => (float) $entry->amount,
                ]);
            }
        }

        $grouped = $rows->groupBy('source')->map(fn ($group, $source) => [
            'source' => $source,
            'count' => $group->count(),
            'hotel' => (float) $group->where('unit', 'hotel')->sum('amount'),
            'food' => (float) $group->where('unit', 'food')->sum('amount'),
            'total' => (float) $group->sum('amount'),
        ])->sortByDesc('total')->values();

        return [
            'rows' => $grouped,
            'totals' => [
                'total' => $grouped->sum('total'),
                'hotel' => $grouped->sum('hotel'),
                'food' => $grouped->sum('food'),
                'sources' => $grouped->count(),
            ],
        ];
    }

    /* ----------------------------------------------------------- receivables */

    private function receivables(): array
    {
        $unit = UnitContext::current()?->slug;

        $query = BillMasterBill::with('company')->orderBy('bill_date');

        if ($unit) {
            $query->where("balance_{$unit}_amount", '>', 0);
        } else {
            $query->where(fn ($q) => $q->where('balance_hotel_amount', '>', 0)->orWhere('balance_food_amount', '>', 0));
        }

        $rows = $query->get()->map(function ($bill) use ($unit) {
            $hotel = (float) $bill->balance_hotel_amount;
            $food = (float) $bill->balance_food_amount;
            $due = $unit === 'hotel' ? $hotel : ($unit === 'food' ? $food : $hotel + $food);
            $days = Carbon::parse($bill->bill_date)->diffInDays(now());

            return [
                'bill_number' => $bill->bill_number,
                'date' => $bill->bill_date,
                'guest' => $bill->guest_name,
                'company' => $bill->company?->name,
                'hotel' => $hotel,
                'food' => $food,
                'due' => $due,
                'days' => $days,
                'bucket' => $days <= 15 ? '0 to 15 days' : ($days <= 30 ? '16 to 30 days' : ($days <= 60 ? '31 to 60 days' : 'Over 60 days')),
            ];
        })->sortByDesc('days')->values();

        return [
            'rows' => $rows,
            'totals' => [
                'due' => $rows->sum('due'),
                'bills' => $rows->count(),
                'buckets' => $rows->groupBy('bucket')->map(fn ($group) => (float) $group->sum('due')),
                'oldest' => $rows->first()['days'] ?? 0,
            ],
        ];
    }

    /* --------------------------------------------------------- advance ledger */

    private function advanceLedger(): array
    {
        $rows = UnitContext::scope($this->inRange(StaffAdvance::class)->with(['staff', 'businessUnit']))
            ->orderBy('date')->get()
            ->map(fn ($advance) => [
                'entry' => $advance->entryNumber(),
                'date' => $advance->date,
                'staff' => $advance->staff?->name ?? 'Not linked',
                'code' => $advance->staff?->employee_code,
                'month' => $advance->year_month,
                'amount' => (float) $advance->amount,
                'unit' => $advance->businessUnit?->name ?? 'Hotel Pallav',
                'recovered' => $this->recoveredFor($advance),
            ]);

        return [
            'rows' => $rows,
            'totals' => [
                'paid' => $rows->sum('amount'),
                'recovered' => $rows->sum('recovered'),
                'outstanding' => $rows->sum('amount') - $rows->sum('recovered'),
                'staff' => $rows->pluck('staff')->unique()->count(),
            ],
        ];
    }

    /** How much of a staff advance has already come back through salary. */
    private function recoveredFor($advance): float
    {
        if (! $advance->employee_id || ! $advance->year_month) {
            return 0.0;
        }

        [$year, $month] = array_pad(explode('-', (string) $advance->year_month), 2, null);

        return (float) SalaryProcessing::where('employee_id', $advance->employee_id)
            ->where('year', (int) $year)->where('month', (int) $month)
            ->sum('advance_deduction');
    }

    /* ---------------------------------------------------------- bill register */

    private function billRegister(): array
    {
        $rows = $this->inRange(BillMasterBill::class, 'bill_date')->with('company')->orderBy('bill_date')->get();

        $advances = $this->inRange(BillMasterAdvance::class, 'payment_date')->sum('hotel_amount')
            + $this->inRange(BillMasterAdvance::class, 'payment_date')->sum('food_amount');

        return [
            'rows' => $rows,
            'totals' => [
                'hotel' => (float) $rows->sum('total_hotel_amount'),
                'food' => (float) $rows->sum('total_food_amount'),
                'total' => (float) $rows->sum('total_hotel_amount') + (float) $rows->sum('total_food_amount'),
                'bills' => $rows->count(),
                'advances' => (float) $advances,
            ],
        ];
    }

    /* ---------------------------------------------------------------- shared */

    /** Every cash movement in the range, tagged in or out. */
    private function movements(): Collection
    {
        $rows = collect();

        $books = [
            ['hotel', HotelCashDeposit::class, 'in', 'Cash deposit', 'depositor'],
            ['food', FoodCashDeposit::class, 'in', 'Cash deposit', 'depositor'],
            ['hotel', HotelCashWithdrawal::class, 'out', 'Cash withdrawal', 'withdrawer'],
            ['food', FoodCashWithdrawal::class, 'out', 'Cash withdrawal', 'withdrawer'],
            ['hotel', HotelMiscExpense::class, 'out', 'Expense', 'expense_name'],
            ['food', FoodMiscExpense::class, 'out', 'Expense', 'expense_name'],
        ];

        foreach ($books as [$unit, $model, $direction, $label, $whoColumn]) {
            if (! UnitContext::shows($unit)) {
                continue;
            }

            foreach ($this->inRange($model)->get() as $entry) {
                $rows->push([
                    'unit' => $unit,
                    'entry' => $entry->entryNumber(),
                    'date' => Carbon::parse($entry->date)->toDateString(),
                    'time' => substr((string) $entry->time, 0, 5),
                    'kind' => $label,
                    'particulars' => $entry->{$whoColumn} ?: $label,
                    'head' => $entry->expense_head ?? $entry->revenue_source ?? null,
                    'direction' => $direction,
                    'amount' => (float) $entry->amount,
                    'by' => $entry->full_name,
                ]);
            }
        }

        // Staff advances are money out of the till as well
        foreach (UnitContext::scope($this->inRange(StaffAdvance::class)->with('staff'))->get() as $advance) {
            $rows->push([
                'unit' => $advance->businessUnit?->slug ?? 'hotel',
                'entry' => $advance->entryNumber(),
                'date' => Carbon::parse($advance->date)->toDateString(),
                'time' => substr((string) $advance->time, 0, 5),
                'kind' => 'Staff advance',
                'particulars' => $advance->staff?->name ?? 'Staff advance',
                'head' => 'Salary advance',
                'direction' => 'out',
                'amount' => (float) $advance->amount,
                'by' => $advance->full_name,
            ]);
        }

        return $rows;
    }

    /** Expense books that are in view, with the column holding their head. */
    private function expenseBooks(): array
    {
        $books = [
            ['hotel', HotelMiscExpense::class, 'expense_name', 'expense_head'],
            ['food', FoodMiscExpense::class, 'expense_name', 'expense_head'],
            ['hotel', HotelCashWithdrawal::class, 'withdrawer', null],
            ['food', FoodCashWithdrawal::class, 'withdrawer', null],
        ];

        return array_values(array_filter($books, fn ($book) => UnitContext::shows($book[0])));
    }

    private function inRange(string $model, string $column = 'date')
    {
        return $model::whereDate($column, '>=', $this->from->toDateString())
            ->whereDate($column, '<=', $this->to->toDateString());
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\FoodCashWithdrawal;
use App\Models\FoodMiscExpense;
use App\Models\HotelCashWithdrawal;
use App\Models\HotelMiscExpense;
use App\Models\StaffAdvance;
use App\Support\CashLedger;
use App\Support\CashPeople;
use App\Support\ForceMode;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cash paid out. One business, two cash books side by side (Hotel and Food),
 * plus staff advances, which belong to the business as a whole. The page shows
 * everything together or narrows to one book or one kind of entry; entries
 * come off a book newest first, and only an Admin or the SuperAdmin can delete.
 */
class ExpenseController extends Controller
{
    /** Every kind of entry: its table, which book it sits in (none for advances) and what it is. */
    public const TYPES = [
        'hotel-withdrawal' => ['model' => HotelCashWithdrawal::class, 'book' => 'hotel', 'kind' => 'withdrawal'],
        'food-withdrawal' => ['model' => FoodCashWithdrawal::class, 'book' => 'food', 'kind' => 'withdrawal'],
        'hotel-misc' => ['model' => HotelMiscExpense::class, 'book' => 'hotel', 'kind' => 'misc'],
        'food-misc' => ['model' => FoodMiscExpense::class, 'book' => 'food', 'kind' => 'misc'],
        'staff-advance' => ['model' => StaffAdvance::class, 'book' => null, 'kind' => 'advance'],
    ];

    private const KINDS = ['withdrawal' => 'Cash Withdrawal', 'misc' => 'Misc. Expense', 'advance' => 'Staff Advance'];

    public function index()
    {
        $book = CashLedger::book();
        $kind = array_key_exists(request('kind'), self::KINDS) ? request('kind') : 'all';
        $user = Auth::user();

        // Staff advances are the whole business's, so they only appear when no single book is picked
        $inBook = fn ($t) => $book === 'all' || $t['book'] === $book;
        $shown = array_filter(self::TYPES, fn ($t) => $inBook($t) && ($kind === 'all' || $t['kind'] === $kind));

        $newest = [];
        $rows = collect();
        foreach ($shown as $type => $t) {
            $newest[$type] = CashLedger::newestId($t['model']);
            $with = $t['kind'] === 'advance' ? ['user', 'staff'] : ['user'];
            $t['model']::with($with)->get()->each(fn ($r) => $rows->push($r->setAttribute('type', $type)));
        }

        $q = CashLedger::query();
        $rows = CashLedger::search($rows, $q, fn ($r) => [
            self::TYPES[$r->type]['book'] ? CashLedger::BOOK_NAMES[self::TYPES[$r->type]['book']] : 'staff',
            self::KINDS[self::TYPES[$r->type]['kind']], optional($r->staff ?? null)->name,
        ]);
        $records = CashLedger::page($rows, CashLedger::per());

        $entries = [];
        $forms = [];
        foreach ($records as $r) {
            $blocked = CashLedger::blocked($user, $r, $newest[$r->type]);
            $key = $r->type.':'.$r->id;

            $entries[] = [
                'key' => $key, 'record' => $r, 'type' => $r->type,
                'book' => self::TYPES[$r->type]['book'], 'kind' => self::TYPES[$r->type]['kind'],
                'canEdit' => $user->canManage($r->user),
                'delete' => ! $blocked ? 'allowed' : ($blocked[0] === 'order' ? 'locked' : 'none'),
            ];
            $forms[$key] = $this->formData($r->type, $r);
        }

        $today = today()->toDateString();
        $month = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        $sum = fn (callable $pick, array $range) => (float) collect(self::TYPES)->filter($pick)
            ->sum(fn ($t) => $t['model']::whereBetween('date', $range)->sum('amount'));

        $stats = [
            'today' => $sum($inBook, [$today, $today]),
            'month' => $sum($inBook, $month),
            'hotelMonth' => $sum(fn ($t) => $t['book'] === 'hotel', $month),
            'foodMonth' => $sum(fn ($t) => $t['book'] === 'food', $month),
        ];

        $blank = [
            'type' => null, 'kind' => $kind === 'all' ? 'withdrawal' : $kind,
            'book' => $book === 'all' ? 'hotel' : $book,
            'values' => ['date' => today()->format('Y-m-d'), 'time' => null, 'year_month' => date('Y-m')],
        ];
        $reopen = old('_form') === 'cashbook' ? $this->formFromOldInput() : null;
        $actions = collect(self::TYPES)->map(fn ($t, $type) => route("expense.$type.store"))->all();
        $activeStaff = Employee::where('is_active', true)->orderBy('name')->get();

        return view('expense.index', compact('records', 'entries', 'forms', 'blank', 'reopen', 'actions', 'stats', 'book', 'kind', 'q', 'activeStaff'));
    }

    private function formData(string $type, ?Model $r): array
    {
        $t = self::TYPES[$type];
        $label = self::KINDS[$t['kind']];

        $values = ['date' => today()->format('Y-m-d'), 'time' => null, 'year_month' => date('Y-m')];
        if ($r) {
            $values = ['date' => $r->date?->format('Y-m-d'), 'time' => substr((string) $r->time, 0, 5), 'amount' => $r->amount] + match ($t['kind']) {
                'withdrawal' => ['withdrawer' => $r->withdrawer],
                'misc' => ['expense_name' => $r->expense_name, 'expense_head' => $r->expense_head, 'instruction' => $r->instruction],
                'advance' => ['employee_id' => $r->employee_id, 'year_month' => $r->year_month, 'instruction' => $r->instruction],
            };
        }

        return [
            'id' => $r?->id, 'type' => $type, 'kind' => $t['kind'], 'book' => $t['book'],
            'title' => $r ? 'Edit '.($t['book'] ? CashLedger::BOOK_NAMES[$t['book']].' ' : '').$label.' #'.$r->entryNumber() : 'New Entry',
            'subtitle' => $r ? $label.' · recorded by '.($r->user?->displayName() ?? $r->full_name).', '.$r->created_at?->format('d M Y H:i') : null,
            'action' => $r ? route("expense.$type.update", $r) : route("expense.$type.store"),
            'values' => $values,
        ];
    }

    /** What was typed when a save was refused, so the pop-up comes back as it was. */
    private function formFromOldInput(): array
    {
        $type = array_key_exists(old('_type'), self::TYPES) ? old('_type') : 'hotel-withdrawal';
        $record = ($id = old('_record_id')) ? self::TYPES[$type]['model']::find($id) : null;
        $data = $this->formData($type, $record);
        $fields = ['date', 'time', 'withdrawer', 'expense_name', 'expense_head', 'employee_id', 'year_month', 'amount', 'instruction'];
        $data['values'] = array_merge($data['values'], array_filter(array_combine($fields, array_map('old', $fields)), fn ($v) => $v !== null));

        return $data;
    }

    private function rules(string $kind): array
    {
        $base = [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'time' => ['required'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ];

        return $base + match ($kind) {
            'withdrawal' => ['withdrawer' => ['required', 'string', 'max:100']],
            'misc' => [
                'expense_name' => ['required', 'string', 'max:100'],
                'expense_head' => ['nullable', 'string', 'max:60'],
                'instruction' => ['nullable', 'string', 'max:500'],
            ],
            'advance' => [
                'employee_id' => ['required', 'exists:employees,id'],
                'year_month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
                'instruction' => ['nullable', 'string', 'max:1000'],
            ],
        };
    }

    /** Validated input shaped for the table: the person settled and remembered, the amount in words. */
    private function payload(Request $request, string $kind): array
    {
        if ($kind === 'withdrawal') {
            // "Other" on the form means a name typed in the box beside it
            $request->merge(['withdrawer' => CashPeople::resolve($request->input('withdrawer'), $request->input('withdrawer_new'))]);
        }

        $data = $request->validate($this->rules($kind));

        if ($kind === 'withdrawal') {
            $data['withdrawer'] = CashPeople::remember($data['withdrawer']);
        }
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);

        return $data;
    }

    private function find(string $type, int|string $id): Model
    {
        return self::TYPES[$type]['model']::findOrFail($id);
    }

    public function store(Request $request, string $type)
    {
        $t = self::TYPES[$type];
        $data = $this->payload($request, $t['kind']);
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;

        $record = $t['model']::create($data);

        return back()->with('success', $this->name($type).' #'.$record->entryNumber().' recorded.');
    }

    public function update(Request $request, string $record, string $type)
    {
        $entry = $this->find($type, $record);

        if (ForceMode::locked(! Auth::user()->canManage($entry->user), 'Not allowed to edit this record')) {
            return back()->with('error', 'You are not allowed to edit this entry.');
        }

        $data = $this->payload($request, self::TYPES[$type]['kind']);
        if (! Auth::user()->isSuperAdmin()) {
            $data['user_id'] = Auth::id();
            $data['full_name'] = Auth::user()->name;
        }
        $entry->update($data);

        return back()->with('success', $this->name($type).' #'.$entry->entryNumber().' updated.');
    }

    public function destroy(string $record, string $type)
    {
        $entry = $this->find($type, $record);

        if ($reason = CashLedger::deleteRefusal(Auth::user(), $entry)) {
            return back()->with('error', $reason);
        }

        $entry->delete();

        return back()->with('success', $this->name($type).' #'.$entry->entryNumber().' deleted.');
    }

    /** "Hotel Cash Withdrawal", "Staff Advance": what an entry is called in messages and on its receipt. */
    private function name(string $type): string
    {
        $t = self::TYPES[$type];

        return ($t['book'] ? CashLedger::BOOK_NAMES[$t['book']].' ' : '').self::KINDS[$t['kind']];
    }

    public function view(string $record, string $type)
    {
        $r = $this->find($type, $record);
        $kind = self::TYPES[$type]['kind'];

        $rows = ['Entry No.' => '#'.$r->entryNumber(), 'Date' => optional($r->date)->format('d-m-Y'), 'Time' => substr((string) $r->time, 0, 5), 'Recorded By' => $r->full_name]
            + match ($kind) {
                'withdrawal' => ['Withdrawer' => $r->withdrawer],
                'misc' => ['Expense' => $r->expense_name, 'Expense Head' => $r->expense_head, 'Instruction' => $r->instruction],
                'advance' => ['Staff Member' => (string) optional($r->staff)->name, 'Month' => $r->year_month, 'Instruction' => $r->instruction],
            }
            + ['Amount' => '₹'.number_format($r->amount, 2), 'Amount in Words' => $r->amount_in_words];

        return Pdf::loadView('pdf.receipt', ['title' => $this->name($type), 'rows' => $rows])->stream($type.'-'.$r->id.'.pdf');
    }
}

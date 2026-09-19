<?php

namespace App\Http\Controllers;

use App\Models\FoodCashDeposit;
use App\Models\HotelCashDeposit;
use App\Support\CashLedger;
use App\Support\CashPeople;
use App\Support\ForceMode;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Cash paid in. One business, two cash books side by side: Hotel and Food.
 * The page shows both together or either one; entries come off a book newest
 * first, and only an Admin or the SuperAdmin can delete.
 */
class RevenueController extends Controller
{
    private const BOOKS = [
        'hotel' => ['model' => HotelCashDeposit::class, 'label' => CashLedger::BOOK_NAMES['hotel']],
        'food' => ['model' => FoodCashDeposit::class, 'label' => CashLedger::BOOK_NAMES['food']],
    ];

    public function index()
    {
        $filter = CashLedger::book();
        $shown = $filter === 'all' ? array_keys(self::BOOKS) : [$filter];
        $user = Auth::user();

        $newest = [];
        $rows = collect();
        foreach ($shown as $book) {
            $model = self::BOOKS[$book]['model'];
            $newest[$book] = CashLedger::newestId($model);
            $model::with('user')->get()->each(fn ($r) => $rows->push($r->setAttribute('book', $book)));
        }

        $q = CashLedger::query();
        $rows = CashLedger::search($rows, $q, fn ($r) => [self::BOOKS[$r->book]['label'], 'deposit']);
        $records = CashLedger::page($rows, CashLedger::per());

        $entries = [];
        $forms = [];
        foreach ($records as $r) {
            $blocked = CashLedger::blocked($user, $r, $newest[$r->book]);
            $key = $r->book.':'.$r->id;

            $entries[] = [
                'key' => $key, 'record' => $r, 'book' => $r->book, 'bookLabel' => self::BOOKS[$r->book]['label'],
                'canEdit' => $user->canManage($r->user),
                'delete' => ! $blocked ? 'allowed' : ($blocked[0] === 'order' ? 'locked' : 'none'),
            ];
            $forms[$key] = $this->formData($r->book, $r);
        }

        $today = today()->toDateString();
        $month = [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()];
        $sum = fn (array $books, array $range) => (float) collect($books)
            ->sum(fn ($b) => self::BOOKS[$b]['model']::whereBetween('date', $range)->sum('amount'));

        $stats = [
            'today' => $sum($shown, [$today, $today]),
            'month' => $sum($shown, $month),
            'hotelMonth' => $sum(['hotel'], $month),
            'foodMonth' => $sum(['food'], $month),
            'count' => $rows->count(),
        ];

        $blank = ['action' => null, 'book' => $filter === 'all' ? 'hotel' : $filter, 'values' => ['date' => today()->format('Y-m-d'), 'time' => null]];
        $reopen = old('_form') === 'cashbook' ? $this->formFromOldInput() : null;
        $actions = collect(self::BOOKS)->map(fn ($b, $key) => route("revenue.$key.store"))->all();

        return view('revenue.index', compact('records', 'entries', 'forms', 'blank', 'reopen', 'actions', 'stats', 'filter', 'q'));
    }

    private function formData(string $book, ?Model $r): array
    {
        return [
            'id' => $r?->id,
            'book' => $book,
            'title' => $r ? 'Edit '.self::BOOKS[$book]['label'].' Deposit #'.$r->entryNumber() : 'New Deposit',
            'subtitle' => $r ? 'Deposit · recorded by '.($r->user?->displayName() ?? $r->full_name).', '.$r->created_at?->format('d M Y H:i') : null,
            'action' => $r ? route("revenue.$book.update", $r) : route("revenue.$book.store"),
            'values' => $r ? [
                'date' => $r->date?->format('Y-m-d'), 'time' => substr((string) $r->time, 0, 5),
                'depositor' => $r->depositor, 'revenue_source' => $r->revenue_source, 'amount' => $r->amount,
            ] : ['date' => today()->format('Y-m-d'), 'time' => null],
        ];
    }

    /** What was typed when a save was refused, so the pop-up comes back as it was. */
    private function formFromOldInput(): array
    {
        $book = in_array(old('_book'), array_keys(self::BOOKS), true) ? old('_book') : 'hotel';
        $record = ($id = old('_record_id')) ? self::BOOKS[$book]['model']::find($id) : null;
        $data = $this->formData($book, $record);
        $data['values'] = array_merge($data['values'], array_filter([
            'date' => old('date'), 'time' => old('time'), 'depositor' => old('depositor'),
            'revenue_source' => old('revenue_source'), 'amount' => old('amount'),
        ], fn ($v) => $v !== null));

        return $data;
    }

    private function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'time' => ['required'],
            'depositor' => ['required', 'string', 'max:100'],
            'revenue_source' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99'],
        ];
    }

    /** Validated input shaped for the table: the depositor settled and remembered, the amount in words. */
    private function payload(Request $request): array
    {
        // "Other" on the form means a name typed in the box beside it
        $request->merge(['depositor' => CashPeople::resolve($request->input('depositor'), $request->input('depositor_new'))]);

        $data = $request->validate($this->rules());
        $data['depositor'] = CashPeople::remember($data['depositor']);
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);

        return $data;
    }

    private function find(string $book, int|string $id): Model
    {
        return self::BOOKS[$book]['model']::findOrFail($id);
    }

    public function store(Request $request, string $book)
    {
        $data = $this->payload($request);
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;

        $record = self::BOOKS[$book]['model']::create($data);

        return back()->with('success', self::BOOKS[$book]['label'].' deposit #'.$record->entryNumber().' recorded.');
    }

    public function update(Request $request, string $record, string $book)
    {
        $deposit = $this->find($book, $record);

        if (ForceMode::locked(! Auth::user()->canManage($deposit->user), 'Not allowed to edit this record')) {
            return back()->with('error', 'You are not allowed to edit this deposit.');
        }

        $deposit->update($this->payload($request));

        return back()->with('success', self::BOOKS[$book]['label'].' deposit #'.$deposit->entryNumber().' updated.');
    }

    public function destroy(string $record, string $book)
    {
        $deposit = $this->find($book, $record);

        if ($reason = CashLedger::deleteRefusal(Auth::user(), $deposit)) {
            return back()->with('error', $reason);
        }

        $deposit->delete();

        return back()->with('success', self::BOOKS[$book]['label'].' deposit #'.$deposit->entryNumber().' deleted.');
    }

    public function view(string $record, string $book)
    {
        $deposit = $this->find($book, $record);
        $label = self::BOOKS[$book]['label'];

        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => $label.' Revenue Receipt',
            'record' => $deposit,
            'rows' => [
                'Entry No.' => '#'.$deposit->entryNumber(),
                'Date' => optional($deposit->date)->format('d-m-Y'),
                'Time' => substr((string) $deposit->time, 0, 5),
                'Recorded By' => $deposit->full_name,
                'Depositor' => $deposit->depositor,
                'Source' => $deposit->revenue_source,
                'Amount' => '₹'.number_format($deposit->amount, 2),
                'Amount in Words' => $deposit->amount_in_words,
            ],
        ]);

        return $pdf->stream($book.'-revenue-'.$deposit->id.'.pdf');
    }
}

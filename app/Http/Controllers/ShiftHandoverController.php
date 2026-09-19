<?php

namespace App\Http\Controllers;

use App\Models\ShiftHandover;
use App\Support\ForceMode;
use App\Support\Masters;
use App\Support\NumberToWords;
use App\Support\RichText;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ShiftHandoverController extends Controller
{
    public function index()
    {
        $search = trim((string) request('q'));

        $records = $this->search(ShiftHandover::with('user'), $search)
            ->latest('date')->latest('time')->latest('id')
            ->paginate(in_array((int) request('per'), [10, 25, 50, 100], true) ? (int) request('per') : 10)
            ->withQueryString();

        $today = ShiftHandover::whereDate('date', today());

        $stats = [
            'today' => (clone $today)->count(),
            'todayCash' => (float) (clone $today)->sum('total'),
            'month' => ShiftHandover::whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
            'latest' => ShiftHandover::with('user')->latest('date')->latest('time')->latest('id')->first(),
        ];

        // Everything the pop-up form needs to open blank, on a record, or
        // back on what was typed when the last save was refused
        $forms = $records->mapWithKeys(fn ($r) => [$r->id => $this->formData($r)]);
        $blank = $this->formData(null);
        $reopen = old('_form') === 'handover' ? $this->formFromOldInput() : null;

        // How the pop-up picks the shift from the time as it changes
        $shiftClock = ['hours' => ShiftHandover::SHIFT_HOURS, 'shifts' => Masters::values('shift')];

        return view('shift_handover.index', compact('records', 'stats', 'forms', 'blank', 'reopen', 'shiftClock', 'search'));
    }

    /**
     * Every word must match something on the handover: who handed over, the
     * shift, a note or instruction, the amount in words, an entry number
     * ("#12"), an amount ("1,500"), or a date (19-09-2026, 19/09/2026,
     * 2026-09-19).
     */
    private function search(Builder $query, string $text): Builder
    {
        foreach (preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $number = str_replace([',', '₹', '#'], '', $word);
            $date = $this->asDate($word);

            $query->where(function (Builder $q) use ($like, $number, $date, $word) {
                $q->where('full_name', 'like', $like)
                    ->orWhere('shift', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('instructions', 'like', $like)
                    ->orWhere('total_in_words', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like));

                if (ctype_digit($number)) {
                    $q->orWhere('entry_no', (int) $number);
                }
                if (is_numeric($number)) {
                    $q->orWhere('total', (float) $number);
                }
                if ($date) {
                    $q->orWhereDate('date', $date);
                }
            });
        }

        return $query;
    }

    private function asDate(string $word): ?string
    {
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $word);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $word) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function formData(?ShiftHandover $r): array
    {
        $counts = [];
        foreach (array_keys(ShiftHandover::DENOMINATIONS) as $denom) {
            $counts[$denom] = (int) ($r?->{"{$denom}_count"} ?? 0);
        }

        return [
            'id' => $r?->id,
            'title' => $r ? 'Edit Handover #'.$r->entryNumber() : 'New Handover',
            'subtitle' => $r ? 'Handover · recorded by '.($r->user?->displayName() ?? $r->full_name).', '.$r->created_at?->format('d M Y H:i') : null,
            'action' => $r ? route('shift-handover.update', $r) : route('shift-handover.store'),
            'date' => $r?->date?->format('Y-m-d') ?? today()->format('Y-m-d'),
            'time' => $r ? substr((string) $r->time, 0, 5) : null,
            // A new one starts on the shift the clock is in right now
            'shift' => $r?->shift ?? ShiftHandover::shiftAt(now()->format('H:i'), Masters::values('shift')) ?? Masters::default('shift'),
            'counts' => $counts,
            'notes' => $r?->noteList() ?? [],
            'instructions' => $r?->instructionList() ?? [],
        ];
    }

    private function formFromOldInput(): array
    {
        $record = ($id = old('_handover_id')) ? ShiftHandover::find($id) : null;
        $data = $this->formData($record);

        foreach (['date' => 'date', 'time' => 'time', 'shift' => 'shift'] as $key => $field) {
            $data[$key] = old($field, $data[$key]);
        }
        foreach (array_keys($data['counts']) as $denom) {
            $data['counts'][$denom] = (int) old("{$denom}_count", 0);
        }
        $data['notes'] = collect((array) old('notes', []))->map(fn ($n) => RichText::inline(is_string($n) ? $n : null))->filter()->values()->all();
        $data['instructions'] = collect((array) old('instructions', []))->filter(fn ($i) => is_string($i) && trim($i) !== '')->values()->all();

        return $data;
    }

    private function rules(): array
    {
        $rules = [
            'date' => ['required', 'date', 'before_or_equal:today'],
            'time' => ['required'],
            'shift' => ['required', 'string', 'max:20'],
            'notes' => ['nullable', 'array', 'max:100'],
            'notes.*' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'array', 'max:100'],
            'instructions.*' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (array_keys(ShiftHandover::DENOMINATIONS) as $denom) {
            $rules["{$denom}_count"] = ['nullable', 'integer', 'min:0', 'max:1000000'];
        }

        return $rules;
    }

    /** Validated input shaped for the table: totals worked out, points tidied. */
    private function payload(Request $request): array
    {
        $data = $request->validate($this->rules());

        $grandTotal = 0;
        foreach (ShiftHandover::DENOMINATIONS as $denom => $value) {
            $count = (int) ($data["{$denom}_count"] ?? 0);
            $data["{$denom}_count"] = (string) $count;
            $data["{$denom}_total"] = $count * $value;
            $grandTotal += $count * $value;
        }
        $data['total'] = $grandTotal;
        $data['total_in_words'] = NumberToWords::convert($grandTotal);

        $data['notes'] = collect($data['notes'] ?? [])
            ->map(fn ($note) => RichText::inline($note))->filter()->values()->all();
        $data['instructions'] = collect($data['instructions'] ?? [])
            ->map(fn ($point) => trim((string) $point))->filter()->values()->all();

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->payload($request);
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;

        $record = ShiftHandover::create($data);

        return redirect()->route('shift-handover.index')
            ->with('success', 'Handover #'.$record->entryNumber().' recorded.');
    }

    public function update(Request $request, ShiftHandover $shiftHandover)
    {
        if (ForceMode::locked(! Auth::user()->canManage($shiftHandover->user), 'Not allowed to edit this record')) {
            return back()->with('error', 'Not allowed to edit this record.');
        }

        $shiftHandover->update($this->payload($request));

        return redirect()->route('shift-handover.index')
            ->with('success', 'Handover #'.$shiftHandover->entryNumber().' updated.');
    }

    public function destroy(ShiftHandover $shiftHandover)
    {
        if (ForceMode::locked(! Auth::user()->canManage($shiftHandover->user), 'Not allowed to delete this record')) {
            return back()->with('error', 'Not allowed to delete this record.');
        }
        $shiftHandover->delete();

        return back()->with('success', 'Deleted.');
    }

    public function view(ShiftHandover $shiftHandover)
    {
        $shiftHandover->loadMissing('user');

        $pdf = Pdf::loadView('shift_handover.pdf', ['record' => $shiftHandover]);

        return $pdf->stream('handover-'.$shiftHandover->entryNumber().'.pdf');
    }
}

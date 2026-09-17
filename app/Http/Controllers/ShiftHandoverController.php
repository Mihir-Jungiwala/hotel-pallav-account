<?php

namespace App\Http\Controllers;

use App\Models\ShiftHandover;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShiftHandoverController extends Controller
{
    public function index()
    {
        $records = ShiftHandover::with('user')->latest('date')->latest('time')->get();

        return view('shift_handover.index', compact('records'));
    }

    private function rules(): array
    {
        $rules = [
            'date' => ['required', 'date'],
            'time' => ['required'],
            'shift' => ['required', 'string', 'max:20'],
            'message_one' => ['nullable', 'string'],
            'message_two' => ['nullable', 'string'],
            'message_three' => ['nullable', 'string'],
            'message_four' => ['nullable', 'string'],
            'message_five' => ['nullable', 'string'],
            'special_instruction' => ['nullable', 'string'],
        ];

        foreach (array_keys(ShiftHandover::DENOMINATIONS) as $denom) {
            $rules["{$denom}_count"] = ['nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    private function computeTotals(array $data): array
    {
        $grandTotal = 0;
        foreach (ShiftHandover::DENOMINATIONS as $denom => $value) {
            $count = (int) ($data["{$denom}_count"] ?? 0);
            $total = $count * $value;
            $data["{$denom}_total"] = $total;
            $data["{$denom}_count"] = (string) $count;
            $grandTotal += $total;
        }
        $data['total'] = $grandTotal;
        $data['total_in_words'] = NumberToWords::convert($grandTotal);

        return $data;
    }

    public function store(Request $request)
    {
        $data = $this->computeTotals($request->validate($this->rules()));
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;

        ShiftHandover::create($data);

        return redirect()->route('shift-handover.index')->with('success', 'Shift handover recorded.');
    }

    public function update(Request $request, ShiftHandover $shiftHandover)
    {
        if (! Auth::user()->canManage($shiftHandover->user)) {
            return back()->with('error', 'Not allowed to edit this record.');
        }

        $data = $this->computeTotals($request->validate($this->rules()));
        $shiftHandover->update($data);

        return back()->with('success', 'Shift handover updated.');
    }

    public function destroy(ShiftHandover $shiftHandover)
    {
        if (! Auth::user()->canManage($shiftHandover->user)) {
            return back()->with('error', 'Not allowed to delete this record.');
        }
        $shiftHandover->delete();

        return back()->with('success', 'Deleted.');
    }

    public function view(ShiftHandover $shiftHandover)
    {
        $rows = [
            'Date' => optional($shiftHandover->date)->format('d-m-Y'),
            'Time' => $shiftHandover->time,
            'Shift' => $shiftHandover->shift,
            'Handed Over By' => $shiftHandover->full_name,
        ];

        foreach (ShiftHandover::DENOMINATIONS as $denom => $value) {
            $label = $denom === 'coins' ? 'Coins' : '₹'.$value.' Notes';
            $rows[$label] = $shiftHandover->{"{$denom}_count"}.' x = ₹'.number_format($shiftHandover->{"{$denom}_total"}, 2);
        }

        $rows['Total Cash'] = '₹'.number_format($shiftHandover->total, 2);
        $rows['Amount in Words'] = $shiftHandover->total_in_words;
        $rows['Special Instruction'] = $shiftHandover->special_instruction;

        $pdf = Pdf::loadView('pdf.receipt', ['title' => 'Shift Handover', 'rows' => $rows]);

        return $pdf->stream('shift-handover-'.$shiftHandover->id.'.pdf');
    }
}

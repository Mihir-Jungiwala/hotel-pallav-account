<?php

namespace App\Http\Controllers;

use App\Models\BillMasterAdvance;
use App\Models\BillMasterBill;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BillMasterController extends Controller
{
    /* ---------------------------------------------------------------
     | Advances
     |----------------------------------------------------------------*/

    public function advances()
    {
        return view('bill_master.advances', [
            'advances' => BillMasterAdvance::with('company')->latest('payment_date')->latest('id')->get(),
            'companies' => CompanyProfile::orderBy('name')->get(),
        ]);
    }

    public function storeAdvance(Request $request)
    {
        $data = $request->validate([
            'receipt_number' => ['required', 'string', 'max:50'],
            'guest_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:15'],
            'company_id' => ['nullable', 'exists:company_profiles,id'],
            'payment_date' => ['required', 'date'],
            'hotel_amount' => ['nullable', 'numeric'],
            'food_amount' => ['nullable', 'numeric'],
            'hotel_mode' => ['nullable', 'string', 'max:20'],
            'food_mode' => ['nullable', 'string', 'max:20'],
            'reference_name' => ['nullable', 'string', 'max:255'],
            'reference_mobile_number' => ['nullable', 'string', 'max:15'],
            'instruction' => ['nullable', 'string', 'max:255'],
        ]);

        $data['receipt_number'] = date('Y').' / '.$data['receipt_number'];

        if (BillMasterAdvance::where('receipt_number', $data['receipt_number'])->exists()) {
            return back()->withErrors(['receipt_number' => 'This receipt number already exists.'])->withInput();
        }

        $data['hotel_balance'] = $data['hotel_amount'] ?? 0;
        $data['food_balance'] = $data['food_amount'] ?? 0;
        $data['total'] = ($data['hotel_amount'] ?? 0) + ($data['food_amount'] ?? 0);
        $data['created_by'] = Auth::id();

        BillMasterAdvance::create($data);

        return redirect()->route('bill-master.advances')->with('success', 'Advance recorded.');
    }

    public function updateAdvance(Request $request, BillMasterAdvance $advance)
    {
        if (! $advance->isUnused() || $advance->isRefunded()) {
            return back()->with('error', 'This advance has already been used or refunded and cannot be edited.');
        }

        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:15'],
            'company_id' => ['nullable', 'exists:company_profiles,id'],
            'payment_date' => ['required', 'date'],
            'hotel_amount' => ['nullable', 'numeric'],
            'food_amount' => ['nullable', 'numeric'],
            'hotel_mode' => ['nullable', 'string', 'max:20'],
            'food_mode' => ['nullable', 'string', 'max:20'],
            'reference_name' => ['nullable', 'string', 'max:255'],
            'reference_mobile_number' => ['nullable', 'string', 'max:15'],
            'instruction' => ['nullable', 'string', 'max:255'],
        ]);

        $data['hotel_balance'] = $data['hotel_amount'] ?? 0;
        $data['food_balance'] = $data['food_amount'] ?? 0;
        $data['total'] = ($data['hotel_amount'] ?? 0) + ($data['food_amount'] ?? 0);

        $advance->update($data);

        return back()->with('success', 'Advance updated.');
    }

    public function destroyAdvance(BillMasterAdvance $advance)
    {
        if (! Auth::user()->canManage($advance->creator)) {
            return back()->with('error', 'Not allowed to delete this record.');
        }

        if (! $advance->isUnused()) {
            return back()->with('error', 'This advance has already been partly used and cannot be deleted.');
        }

        $advance->delete();

        return back()->with('success', 'Advance deleted.');
    }

    public function refundAdvance(Request $request, BillMasterAdvance $advance)
    {
        if ($advance->isRefunded()) {
            return back()->with('error', 'This advance has already been refunded.');
        }

        $data = $request->validate([
            'hotel_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'food_refund_amount' => ['nullable', 'numeric', 'min:0'],
            'hotel_refund_mode' => ['nullable', 'string', 'max:20'],
            'food_refund_mode' => ['nullable', 'string', 'max:20'],
            'refund_payment_date' => ['required', 'date'],
            'refund_guest_name' => ['nullable', 'string', 'max:255'],
            'refund_mobile_number' => ['nullable', 'string', 'max:15'],
            'refund_instruction' => ['nullable', 'string', 'max:255'],
        ]);

        $hotelRefund = $data['hotel_refund_amount'] ?? 0;
        $foodRefund = $data['food_refund_amount'] ?? 0;

        if ($hotelRefund > $advance->hotel_balance || $foodRefund > $advance->food_balance) {
            return back()->with('error', 'Refund amount cannot exceed the available advance balance.')->withInput();
        }

        $data['hotel_balance'] = $advance->hotel_balance - $hotelRefund;
        $data['food_balance'] = $advance->food_balance - $foodRefund;
        $data['total'] = $hotelRefund + $foodRefund;

        $advance->update($data);

        return back()->with('success', 'Refund recorded.');
    }

    public function destroyRefund(BillMasterAdvance $advance)
    {
        $advance->update([
            'hotel_balance' => $advance->hotel_balance + $advance->hotel_refund_amount,
            'food_balance' => $advance->food_balance + $advance->food_refund_amount,
            'total' => ($advance->hotel_balance + $advance->hotel_refund_amount) + ($advance->food_balance + $advance->food_refund_amount),
            'hotel_refund_amount' => null,
            'food_refund_amount' => null,
            'hotel_refund_mode' => null,
            'food_refund_mode' => null,
            'refund_payment_date' => null,
            'refund_guest_name' => null,
            'refund_mobile_number' => null,
            'refund_instruction' => null,
        ]);

        return back()->with('success', 'Refund reversed.');
    }

    /* ---------------------------------------------------------------
     | Bills
     |----------------------------------------------------------------*/

    public function bills()
    {
        return view('bill_master.bills', [
            'bills' => BillMasterBill::with('company')->latest('bill_date')->latest('id')->get(),
            'companies' => CompanyProfile::orderBy('name')->get(),
            'advances' => BillMasterAdvance::orderBy('guest_name')->get(),
        ]);
    }

    private function billRules(): array
    {
        return [
            'bill_number' => ['required', 'string', 'max:50'],
            'company_id' => ['nullable', 'exists:company_profiles,id'],
            'bill_date' => ['required', 'date'],
            'guest_name' => ['required', 'string', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:15'],
            'advance_id' => ['nullable', 'exists:bill_master_advances,id'],
            'hotel_plan' => ['nullable', 'string', 'max:100'],
            'hotel_amount' => ['nullable', 'numeric'],
            'hotel_plan_amount' => ['nullable', 'numeric'],
            'hotel_laundry_amount' => ['nullable', 'numeric'],
            'hotel_gst' => ['nullable', 'numeric'],
            'hotel_mode_of_payment' => ['nullable', 'string', 'max:100'],
            'food_plan_amount' => ['nullable', 'numeric'],
            'food_laundry_amount' => ['nullable', 'numeric'],
            'food_amount' => ['nullable', 'numeric'],
            'food_gst' => ['nullable', 'numeric'],
            'food_mode_of_payment' => ['nullable', 'string', 'max:100'],
            'reference_name' => ['nullable', 'string', 'max:100'],
            'reference_mobile_number' => ['nullable', 'string', 'max:15'],
            'instruction' => ['nullable', 'string'],
            'invoice_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:8192'],
        ];
    }

    public function storeBill(Request $request)
    {
        $data = $request->validate($this->billRules());
        $data['bill_number'] = date('Y').' / '.$data['bill_number'];

        if (BillMasterBill::where('bill_number', $data['bill_number'])->exists()) {
            return back()->withErrors(['bill_number' => 'This bill number already exists.'])->withInput();
        }

        $hotelTotal = ($data['hotel_amount'] ?? 0) + ($data['hotel_plan_amount'] ?? 0) + ($data['hotel_laundry_amount'] ?? 0) + ($data['hotel_gst'] ?? 0);
        $foodTotal = ($data['food_amount'] ?? 0) + ($data['food_plan_amount'] ?? 0) + ($data['food_laundry_amount'] ?? 0) + ($data['food_gst'] ?? 0);
        $data['total_hotel_amount'] = $hotelTotal;
        $data['total_food_amount'] = $foodTotal;

        $advance = null;
        if (! empty($data['advance_id'])) {
            $advance = BillMasterAdvance::find($data['advance_id']);
        }

        if ($advance) {
            $data['advance_receipt_number'] = $advance->receipt_number;
            $data['advance_guest_name'] = $advance->guest_name;
            $data['advance_hotel_amount_snapshot'] = (string) $advance->hotel_amount;
            $data['advance_food_amount_snapshot'] = (string) $advance->food_amount;
            $data['advance_date_snapshot'] = optional($advance->payment_date)->toDateString();
            $data['advance_company_snapshot'] = optional($advance->company)->name;

            $data['advance_hotel_amount'] = min($advance->hotel_balance, $hotelTotal);
            $data['advance_food_amount'] = min($advance->food_balance, $foodTotal);
            $data['advance_delete_hotel_amount'] = $data['advance_hotel_amount'];
            $data['advance_delete_food_amount'] = $data['advance_food_amount'];

            $advance->hotel_balance = max(0, $advance->hotel_balance - $hotelTotal);
            $advance->food_balance = max(0, $advance->food_balance - $foodTotal);
            $advance->save();
        }

        $data['balance_hotel_amount'] = strtolower((string) ($data['hotel_mode_of_payment'] ?? '')) === 'debit' ? $hotelTotal : 0;
        $data['balance_food_amount'] = strtolower((string) ($data['food_mode_of_payment'] ?? '')) === 'debit' ? $foodTotal : 0;

        if ($request->hasFile('invoice_pdf')) {
            $data['invoice_pdf_path'] = $request->file('invoice_pdf')->store('invoice_debit_bill', 'public');
        }

        $data['created_by'] = Auth::id();

        BillMasterBill::create($data);

        return redirect()->route('bill-master.bills')->with('success', 'Bill created.');
    }

    public function updateBill(Request $request, BillMasterBill $bill)
    {
        if ($bill->hasDebitBillRecorded()) {
            return back()->with('error', 'A debit bill has already been generated for this bill; it cannot be edited.');
        }

        $data = $request->validate($this->billRules());
        unset($data['bill_number']);

        $hotelTotal = ($data['hotel_amount'] ?? 0) + ($data['hotel_plan_amount'] ?? 0) + ($data['hotel_laundry_amount'] ?? 0) + ($data['hotel_gst'] ?? 0);
        $foodTotal = ($data['food_amount'] ?? 0) + ($data['food_plan_amount'] ?? 0) + ($data['food_laundry_amount'] ?? 0) + ($data['food_gst'] ?? 0);
        $data['total_hotel_amount'] = $hotelTotal;
        $data['total_food_amount'] = $foodTotal;

        $data['balance_hotel_amount'] = strtolower((string) ($data['hotel_mode_of_payment'] ?? '')) === 'debit' ? $hotelTotal : 0;
        $data['balance_food_amount'] = strtolower((string) ($data['food_mode_of_payment'] ?? '')) === 'debit' ? $foodTotal : 0;

        if ($request->hasFile('invoice_pdf')) {
            $data['invoice_pdf_path'] = $request->file('invoice_pdf')->store('invoice_debit_bill', 'public');
        }

        $bill->update($data);

        return back()->with('success', 'Bill updated.');
    }

    public function destroyBill(BillMasterBill $bill)
    {
        if ($bill->hasDebitBillRecorded()) {
            return back()->with('error', 'A debit bill has already been generated for this bill; it cannot be deleted.');
        }

        if ($bill->advance_id && $advance = BillMasterAdvance::find($bill->advance_id)) {
            $advance->hotel_balance += $bill->advance_delete_hotel_amount;
            $advance->food_balance += $bill->advance_delete_food_amount;
            $advance->save();
        }

        $bill->delete();

        return back()->with('success', 'Bill deleted.');
    }

    /* ---------------------------------------------------------------
     | Debit Bills (installment settlement)
     |----------------------------------------------------------------*/

    public function debitBills()
    {
        $bills = BillMasterBill::with('company')
            ->where(function ($q) {
                $q->whereRaw('LOWER(hotel_mode_of_payment) = ?', ['debit'])
                    ->orWhereRaw('LOWER(food_mode_of_payment) = ?', ['debit']);
            })
            ->latest('bill_date')
            ->get();

        return view('bill_master.debit-bills', compact('bills'));
    }

    public function storeDebitBill(Request $request, BillMasterBill $bill)
    {
        if ($bill->hasDebitBillRecorded()) {
            return back()->with('error', 'Debit bill already recorded for this bill.');
        }

        $rules = [
            'debit_reference_name' => ['nullable', 'string', 'max:100'],
            'debit_reference_mobile_number' => ['nullable', 'string', 'max:15'],
            'debit_instruction' => ['nullable', 'string'],
        ];

        foreach (range(0, 4) as $i) {
            $suffix = $i === 0 ? '' : "_$i";
            $rules["debit_bill_date{$suffix}"] = ['nullable', 'date'];
            $rules["debit_hotel_mode{$suffix}"] = ['nullable', 'string', 'max:20'];
            $rules["debit_food_mode{$suffix}"] = ['nullable', 'string', 'max:20'];
            $rules["debit_hotel_amount{$suffix}"] = ['nullable', 'numeric'];
            $rules["debit_food_amount{$suffix}"] = ['nullable', 'numeric'];
        }

        $data = $request->validate($rules);

        $paidHotel = 0;
        $paidFood = 0;
        foreach (range(0, 4) as $i) {
            $suffix = $i === 0 ? '' : "_$i";
            $paidHotel += (float) ($data["debit_hotel_amount{$suffix}"] ?? 0);
            $paidFood += (float) ($data["debit_food_amount{$suffix}"] ?? 0);
        }

        $remainingHotel = $bill->balance_hotel_amount - $paidHotel;
        $remainingFood = $bill->balance_food_amount - $paidFood;

        $data['debit_hotel_advance'] = (string) max(0, -$remainingHotel);
        $data['debit_food_advance'] = (string) max(0, -$remainingFood);
        $data['balance_hotel_amount'] = max(0, $remainingHotel);
        $data['balance_food_amount'] = max(0, $remainingFood);

        $bill->update($data);

        $excessHotel = max(0, -$remainingHotel);
        $excessFood = max(0, -$remainingFood);

        if ($excessHotel > 0 || $excessFood > 0) {
            $receiptNumber = 'Excessive / '.$bill->bill_number;

            BillMasterAdvance::create([
                'receipt_number' => $receiptNumber,
                'guest_name' => $bill->guest_name,
                'mobile_number' => $bill->mobile_number,
                'company_id' => $bill->company_id,
                'payment_date' => now()->toDateString(),
                'hotel_amount' => $excessHotel,
                'food_amount' => $excessFood,
                'hotel_balance' => $excessHotel,
                'food_balance' => $excessFood,
                'total' => $excessHotel + $excessFood,
                'created_by' => Auth::id(),
            ]);

            $bill->formatted_advance_receipt_number = $receiptNumber;
            $bill->save();
        }

        return back()->with('success', 'Debit bill settlement recorded.');
    }

    public function destroyDebitBill(BillMasterBill $bill)
    {
        if ($bill->formatted_advance_receipt_number) {
            BillMasterAdvance::where('receipt_number', $bill->formatted_advance_receipt_number)->delete();
        }

        $reset = ['formatted_advance_receipt_number' => null, 'debit_hotel_advance' => null, 'debit_food_advance' => null];
        foreach (range(0, 4) as $i) {
            $suffix = $i === 0 ? '' : "_$i";
            $reset["debit_bill_date{$suffix}"] = null;
            $reset["debit_hotel_mode{$suffix}"] = null;
            $reset["debit_food_mode{$suffix}"] = null;
            $reset["debit_hotel_amount{$suffix}"] = null;
            $reset["debit_food_amount{$suffix}"] = null;
        }
        $reset['balance_hotel_amount'] = $bill->total_hotel_amount;
        $reset['balance_food_amount'] = $bill->total_food_amount;

        $bill->update($reset);

        return back()->with('success', 'Debit bill settlement reversed.');
    }
}

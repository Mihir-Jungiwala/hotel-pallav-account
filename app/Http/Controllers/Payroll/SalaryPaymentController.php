<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\SalaryProcessing;
use App\Support\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalaryPaymentController extends Controller
{
    /** Bulk updates can't set a partial amount, since each employee's salary differs. */
    private const BULK_STATUSES = ['Pending', 'Processing', 'On Hold', 'Paid', 'Failed'];

    public function update(Request $request, SalaryProcessing $processing)
    {
        \App\Support\PayrollScope::ensure($processing);

        $this->guardAccess($processing);

        $data = $request->validate([
            'payment_status' => ['required', Rule::in(array_keys(SalaryProcessing::PAYMENT_STATUSES))],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_remarks' => ['nullable', 'string', 'max:500'],
        ], [
            'paid_at.before_or_equal' => 'The payment date cannot be in the future.',
        ]);

        $this->applyPayment($processing, $data);

        return back()->with('success', $processing->employee_name.' marked '.$processing->payment_status.'.');
    }

    public function bulk(Request $request)
    {
        $company = PayrollContext::currentOrFail();
        $this->guardRole();

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'payment_status' => ['required', Rule::in(self::BULK_STATUSES)],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ], [
            'ids.required' => 'Select at least one employee.',
        ]);

        $processings = SalaryProcessing::where('payroll_company_id', $company->id)
            ->whereIn('id', $data['ids'])->get();

        DB::transaction(function () use ($processings, $data) {
            foreach ($processings as $processing) {
                $this->applyPayment($processing, $data);
            }
        });

        return back()->with('success', $processings->count().' payment(s) marked '.$data['payment_status'].'.');
    }

    /**
     * Payment register for a month, ready to print or share.
     */
    public function download(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $start = Carbon::create((int) $request->input('year'), (int) $request->input('month'), 1);

        $payments = SalaryProcessing::with('paymentUpdater')
            ->where('payroll_company_id', $company->id)
            ->where('year', $start->year)->where('month', $start->month)
            ->orderBy('employee_name')->get();

        abort_if($payments->isEmpty(), 404, 'No salary processed for that month.');

        return Pdf::loadView('payroll.pdf.payment-register', compact('company', 'payments', 'start'))
            ->setPaper('a4', 'landscape')
            ->stream('salary-payments-'.$start->format('Y-m').'.pdf');
    }

    private function applyPayment(SalaryProcessing $processing, array $data): void
    {
        $status = $data['payment_status'];
        $net = (float) $processing->net_salary;

        if ($status === 'Paid') {
            $paidAmount = $net;
        } elseif ($status === 'Partially Paid') {
            $paidAmount = (float) ($data['paid_amount'] ?? 0);

            if ($paidAmount <= 0 || $paidAmount >= $net) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'A partial payment must be more than ₹0 and less than the net salary of ₹'.number_format($net, 2).'.',
                ]);
            }
        } else {
            $paidAmount = 0;
        }

        $settles = in_array($status, SalaryProcessing::SETTLING_STATUSES, true);

        $processing->forceFill([
            'payment_status' => $status,
            'paid_amount' => $paidAmount,
            'paid_at' => $settles ? ($data['paid_at'] ?? now()->toDateString()) : null,
            'payment_reference' => $data['payment_reference'] ?? ($settles ? $processing->payment_reference : null),
            'payment_remarks' => array_key_exists('payment_remarks', $data) ? $data['payment_remarks'] : $processing->payment_remarks,
            'payment_updated_by' => Auth::id(),
            'payment_updated_at' => now(),
        ])->save();
    }

    private function guardAccess(SalaryProcessing $processing): void
    {
        $this->guardRole();

        $company = PayrollContext::current();
        abort_if($company && $processing->payroll_company_id !== $company->id, 404);
    }

    private function guardRole(): void
    {
        abort_if(Auth::user()->role === 'Viewer', 403, 'Viewers cannot change payment status.');
    }
}

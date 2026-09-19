<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\SalaryProcessing;
use Barryvdh\DomPDF\Facade\Pdf;

class SalarySlipController extends Controller
{
    public function index()
    {
        $company = \App\Support\PayrollContext::currentOrFail();

        return view('payroll.pages.salary-slip', [
            'company' => $company,
            // Most recent pay period first, newest processing within it
            'processings' => SalaryProcessing::with('employee')
                ->where('payroll_company_id', $company->id)
                ->orderByDesc('year')->orderByDesc('month')
                ->orderByDesc('processed_at')->orderByDesc('id')
                ->get(),
        ]);
    }

    public function view(SalaryProcessing $processing)
    {
        \App\Support\PayrollScope::ensure($processing);

        $processing->load('lines', 'company', 'employee');

        return \App\Support\PayrollPdf::make('payroll.pdf.salary-slip', [
            'processing' => $processing,
            'company' => $processing->company,
        ])->stream('salary-slip-'.$processing->employee_code.'-'.$processing->year.'-'.$processing->month.'.pdf');
    }
}

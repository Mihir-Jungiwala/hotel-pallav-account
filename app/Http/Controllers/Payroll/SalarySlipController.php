<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\SalaryProcessing;
use Barryvdh\DomPDF\Facade\Pdf;

class SalarySlipController extends Controller
{
    public function view(SalaryProcessing $processing)
    {
        $processing->load('lines', 'company', 'employee');

        return Pdf::loadView('payroll.pdf.salary-slip', [
            'processing' => $processing,
            'company' => $processing->company,
        ])->stream('salary-slip-'.$processing->employee_code.'-'.$processing->year.'-'.$processing->month.'.pdf');
    }
}

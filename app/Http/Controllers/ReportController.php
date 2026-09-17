<?php

namespace App\Http\Controllers;

use App\Models\BillMasterBill;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = BillMasterBill::with('company')->latest('bill_date');

        if ($request->filled('from_date')) {
            $query->whereDate('bill_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('bill_date', '<=', $request->to_date);
        }

        $bills = $query->get();

        return view('reports.index', compact('bills'));
    }
}

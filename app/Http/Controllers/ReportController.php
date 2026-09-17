<?php

namespace App\Http\Controllers;

use App\Support\UnitContext;
use App\Models\BillMasterBill;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $query = BillMasterBill::with('company')->latest('bill_date');

        // Only the bills that carry an amount for the business in view
        if ($unit = UnitContext::current()) {
            $side = $unit->slug;
            $query->where(function ($q) use ($side) {
                $q->where("total_{$side}_amount", '>', 0)
                    ->orWhere("debit_{$side}_amount", '>', 0)
                    ->orWhere("balance_{$side}_amount", '>', 0);
            });
        }

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

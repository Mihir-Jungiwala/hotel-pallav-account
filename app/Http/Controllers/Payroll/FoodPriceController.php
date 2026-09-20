<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\FoodChargeRate;
use App\Models\PayrollCompany;
use App\Support\FoodCharges;
use App\Support\PayrollContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Food Price: what Pallav Food charges per employee per month. Pallav Food
 * decides it and only Pallav Food's payroll shows this page - Hotel Pallav
 * sees the price and the bill, never the means to change it.
 *
 * It works like a salary revision: nothing is overwritten. A change starts on
 * the day it is entered and the old price stays on record, so a bill for an
 * earlier month still adds up to what it did. And once salary has been
 * generated for a month, in either company, that month is closed: no price can
 * start in it, and none that is already in it can be removed.
 */
class FoodPriceController extends Controller
{
    private function company(): PayrollCompany
    {
        $company = PayrollContext::currentOrFail();

        abort_unless($company->providesFood(), 404);

        return $company;
    }

    public function index()
    {
        $company = $this->company();
        $rates = FoodCharges::rates();
        $firstOpen = FoodCharges::firstOpenMonth();
        $lockedThrough = FoodCharges::lockedThrough();

        return view('payroll.pages.food-price', [
            'company' => $company,
            'current' => FoodCharges::currentRate(),
            // Newest first, each with what it replaced, like a salary history
            'history' => $rates->reverse()->values()->map(function (FoodChargeRate $rate, int $i) use ($rates) {
                $index = $rates->search(fn ($r) => $r->id === $rate->id);
                $before = $index > 0 ? $rates[$index - 1] : null;

                return (object) [
                    'rate' => $rate,
                    'was' => $before ? (float) $before->monthly_amount : null,
                    'locked' => FoodCharges::isLocked($rate->effective_from),
                    'inForce' => FoodCharges::rateOn(now()->startOfDay(), $rates) !== null
                        && $rate->id === $rates->filter(fn ($r) => $r->effective_from->lessThanOrEqualTo(now()->startOfDay()))->last()?->id,
                ];
            }),
            'lockedThrough' => $lockedThrough,
            'firstOpen' => $firstOpen,
            // Where the date starts: today, the usual case (a change from now on), unless
            // today is somehow inside a closed month, in which case the first open one
            'earliest' => now()->startOfDay()->greaterThan($firstOpen) ? now()->startOfDay() : $firstOpen,
        ]);
    }

    public function store(Request $request)
    {
        $this->company();

        $data = $request->validate([
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'effective_from' => ['required', 'date'],
        ]);

        $from = Carbon::parse($data['effective_from'])->startOfDay();

        if (FoodCharges::isLocked($from)) {
            return back()->withInput()->withErrors(['effective_from' => $this->closed($from)]);
        }

        // Entering a second price for the same day replaces the first: it is a
        // correction made the same day, not a change worth keeping twice
        $existing = FoodChargeRate::where('payroll_company_id', PayrollCompany::foodProvider()->id)
            ->whereDate('effective_from', $from)->first();

        if ($existing) {
            $existing->update(['monthly_amount' => $data['monthly_amount']]);
        } else {
            FoodChargeRate::create([
                'payroll_company_id' => PayrollCompany::foodProvider()->id,
                'monthly_amount' => $data['monthly_amount'],
                'effective_from' => $from,
                'created_by' => Auth::id(),
            ]);
        }

        return back()->with('success', 'The price is now Rs '.number_format((float) $data['monthly_amount'], 2)
            .' per employee from '.$from->format('j F Y').'. The earlier price stays on record.');
    }

    public function destroy(FoodChargeRate $rate)
    {
        $provider = $this->company();

        abort_unless($rate->payroll_company_id === $provider->id, 404);

        if (FoodCharges::isLocked($rate->effective_from)) {
            return back()->with('error', $this->closed($rate->effective_from));
        }

        $rate->delete();

        return back()->with('success', 'That price was removed. Days it covered fall back to the price before it.');
    }

    private function closed(Carbon $date): string
    {
        $open = FoodCharges::firstOpenMonth();

        return 'Salary has already been generated for '.$date->format('F Y').', so its food price is closed. '
            .'A new price can start from '.$open->format('F Y').' onwards.';
    }
}

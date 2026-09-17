<?php

namespace App\Http\Controllers;

use App\Models\BillMasterBill;
use App\Models\CompanyProfile;
use App\Models\FoodCashDeposit;
use App\Models\FoodCashWithdrawal;
use App\Models\FoodMiscExpense;
use App\Models\HotelCashDeposit;
use App\Models\HotelCashWithdrawal;
use App\Models\HotelMiscExpense;
use App\Models\Employee;
use App\Models\ShiftHandover;
use App\Models\StaffAdvance;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today()->toDateString();

        $totalStaff = Employee::where('is_active', true)->count();
        $totalCompany = CompanyProfile::count();
        $shiftHandover = ShiftHandover::latest('date')->latest('time')->first();

        $hotelExpense = HotelCashWithdrawal::whereDate('date', $today)->sum('amount')
            + HotelMiscExpense::whereDate('date', $today)->sum('amount');

        $foodExpense = FoodCashWithdrawal::whereDate('date', $today)->sum('amount')
            + FoodMiscExpense::whereDate('date', $today)->sum('amount')
            + StaffAdvance::whereDate('date', $today)->sum('amount');

        $hotelIncome = HotelCashDeposit::whereDate('date', $today)->sum('amount')
            + \App\Models\BillMasterAdvance::whereDate('payment_date', $today)->whereRaw('LOWER(hotel_mode) = ?', ['cash'])->sum('hotel_amount')
            + BillMasterBill::whereDate('bill_date', $today)->whereRaw('LOWER(hotel_mode_of_payment) = ?', ['cash'])->sum('total_hotel_amount')
            + BillMasterBill::whereDate('bill_date', $today)->whereRaw('LOWER(hotel_mode_of_payment) = ?', ['debit'])->sum('debit_hotel_amount');

        $foodIncome = FoodCashDeposit::whereDate('date', $today)->sum('amount')
            + \App\Models\BillMasterAdvance::whereDate('payment_date', $today)->whereRaw('LOWER(food_mode) = ?', ['cash'])->sum('food_amount')
            + BillMasterBill::whereDate('bill_date', $today)->whereRaw('LOWER(food_mode_of_payment) = ?', ['cash'])->sum('total_food_amount')
            + BillMasterBill::whereDate('bill_date', $today)->whereRaw('LOWER(food_mode_of_payment) = ?', ['debit'])->sum('debit_food_amount');

        $totalDebitHotelBills = BillMasterBill::whereDate('bill_date', $today)
            ->whereRaw('LOWER(hotel_mode_of_payment) = ?', ['debit'])->where('balance_hotel_amount', '>', 0)->count();

        $totalDebitFoodBills = BillMasterBill::whereDate('bill_date', $today)
            ->whereRaw('LOWER(food_mode_of_payment) = ?', ['debit'])->where('balance_food_amount', '>', 0)->count();

        return view('dashboard.index', [
            'totalStaff' => $totalStaff,
            'totalCompany' => $totalCompany,
            'shiftHandover' => $shiftHandover,
            'hotelExpense' => $hotelExpense,
            'foodExpense' => $foodExpense,
            'hotelIncome' => $hotelIncome,
            'foodIncome' => $foodIncome,
            'hotelBalance' => $hotelIncome - $hotelExpense,
            'foodBalance' => $foodIncome - $foodExpense,
            'totalDebitHotelBills' => $totalDebitHotelBills,
            'totalDebitFoodBills' => $totalDebitFoodBills,
        ]);
    }
}

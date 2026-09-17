<?php

namespace App\Http\Controllers;

use App\Models\FoodCashWithdrawal;
use App\Models\FoodMiscExpense;
use App\Models\HotelCashWithdrawal;
use App\Models\HotelMiscExpense;
use App\Models\Employee;
use App\Models\StaffAdvance;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpenseController extends Controller
{
    public function index()
    {
        return view('expense.index', [
            'hotelWithdrawals' => HotelCashWithdrawal::with('user')->latest('date')->latest('time')->get(),
            'foodWithdrawals' => FoodCashWithdrawal::with('user')->latest('date')->latest('time')->get(),
            'hotelMisc' => HotelMiscExpense::with('user')->latest('date')->latest('time')->get(),
            'foodMisc' => FoodMiscExpense::with('user')->latest('date')->latest('time')->get(),
            'staffAdvances' => StaffAdvance::with(['user', 'staff'])->latest('date')->latest('time')->get(),
            'activeStaff' => Employee::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function withdrawalRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time' => ['required'],
            'withdrawer' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    private function miscRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time' => ['required'],
            'expense_name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'instruction' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function advanceRules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time' => ['required'],
            'employee_id' => ['required', 'exists:employees,id'],
            'year_month' => ['required', 'string', 'max:7'],
            'amount' => ['required', 'numeric', 'min:0'],
            'instruction' => ['nullable', 'string'],
        ];
    }

    public function storeHotelWithdrawal(Request $request)
    {
        $data = $request->validate($this->withdrawalRules());
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        HotelCashWithdrawal::create($data);

        return back()->with('success', 'Hotel cash withdrawal recorded.');
    }

    public function storeFoodWithdrawal(Request $request)
    {
        $data = $request->validate($this->withdrawalRules());
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        FoodCashWithdrawal::create($data);

        return back()->with('success', 'Food cash withdrawal recorded.');
    }

    public function destroyHotelWithdrawal(HotelCashWithdrawal $withdrawal)
    {
        if (! Auth::user()->canManage($withdrawal->user)) {
            return back()->with('error', 'Not allowed to delete this record.');
        }
        $withdrawal->delete();

        return back()->with('success', 'Deleted.');
    }

    public function destroyFoodWithdrawal(FoodCashWithdrawal $withdrawal)
    {
        if (! Auth::user()->canManage($withdrawal->user)) {
            return back()->with('error', 'Not allowed to delete this record.');
        }
        $withdrawal->delete();

        return back()->with('success', 'Deleted.');
    }

    public function storeHotelMisc(Request $request)
    {
        $data = $request->validate($this->miscRules());
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        HotelMiscExpense::create($data);

        return back()->with('success', 'Hotel miscellaneous expense recorded.');
    }

    public function updateHotelMisc(Request $request, HotelMiscExpense $expense)
    {
        if (! Auth::user()->canManage($expense->user)) {
            return back()->with('error', 'Not allowed to edit this record.');
        }
        $data = $request->validate($this->miscRules());
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        if (! Auth::user()->isSuperAdmin()) {
            $data['user_id'] = Auth::id();
            $data['full_name'] = Auth::user()->name;
        }
        $expense->update($data);

        return back()->with('success', 'Updated.');
    }

    public function destroyHotelMisc(HotelMiscExpense $expense)
    {
        if (! Auth::user()->canManage($expense->user)) {
            return back()->with('error', 'Not allowed to delete this record.');
        }
        $expense->delete();

        return back()->with('success', 'Deleted.');
    }

    public function storeFoodMisc(Request $request)
    {
        $data = $request->validate($this->miscRules());
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        FoodMiscExpense::create($data);

        return back()->with('success', 'Food miscellaneous expense recorded.');
    }

    public function updateFoodMisc(Request $request, FoodMiscExpense $expense)
    {
        if (! Auth::user()->canManage($expense->user)) {
            return back()->with('error', 'Not allowed to edit this record.');
        }
        $data = $request->validate($this->miscRules());
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        if (! Auth::user()->isSuperAdmin()) {
            $data['user_id'] = Auth::id();
            $data['full_name'] = Auth::user()->name;
        }
        $expense->update($data);

        return back()->with('success', 'Updated.');
    }

    public function destroyFoodMisc(FoodMiscExpense $expense)
    {
        if (! Auth::user()->canManage($expense->user)) {
            return back()->with('error', 'Not allowed to delete this record.');
        }
        $expense->delete();

        return back()->with('success', 'Deleted.');
    }

    public function storeStaffAdvance(Request $request)
    {
        $data = $request->validate($this->advanceRules());
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        StaffAdvance::create($data);

        return back()->with('success', 'Staff advance recorded.');
    }

    public function updateStaffAdvance(Request $request, StaffAdvance $advance)
    {
        if (! Auth::user()->canManage($advance->user)) {
            return back()->with('error', 'Not allowed to edit this record.');
        }
        $data = $request->validate($this->advanceRules());
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);
        $advance->update($data);

        return back()->with('success', 'Updated.');
    }

    public function destroyStaffAdvance(StaffAdvance $advance)
    {
        if (! Auth::user()->canManage($advance->user)) {
            return back()->with('error', 'Not allowed to delete this record.');
        }
        $advance->delete();

        return back()->with('success', 'Deleted.');
    }

    private function pdfRows(string $personLabel, string $personValue, $record, array $extra = []): array
    {
        return array_merge([
            'Date' => optional($record->date)->format('d-m-Y'),
            'Time' => $record->time,
            'Recorded By' => $record->full_name,
            $personLabel => $personValue,
        ], $extra, [
            'Amount' => '₹'.number_format($record->amount, 2),
            'Amount in Words' => $record->amount_in_words,
        ]);
    }

    public function viewHotelWithdrawal(HotelCashWithdrawal $withdrawal)
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => 'Hotel Cash Withdrawal',
            'rows' => $this->pdfRows('Withdrawer', $withdrawal->withdrawer, $withdrawal),
        ]);

        return $pdf->stream('hotel-withdrawal-'.$withdrawal->id.'.pdf');
    }

    public function viewFoodWithdrawal(FoodCashWithdrawal $withdrawal)
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => 'Food Cash Withdrawal',
            'rows' => $this->pdfRows('Withdrawer', $withdrawal->withdrawer, $withdrawal),
        ]);

        return $pdf->stream('food-withdrawal-'.$withdrawal->id.'.pdf');
    }

    public function viewHotelMisc(HotelMiscExpense $expense)
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => 'Hotel Miscellaneous Expense',
            'rows' => $this->pdfRows('Expense', $expense->expense_name, $expense, ['Instruction' => $expense->instruction]),
        ]);

        return $pdf->stream('hotel-misc-expense-'.$expense->id.'.pdf');
    }

    public function viewFoodMisc(FoodMiscExpense $expense)
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => 'Food Miscellaneous Expense',
            'rows' => $this->pdfRows('Expense', $expense->expense_name, $expense, ['Instruction' => $expense->instruction]),
        ]);

        return $pdf->stream('food-misc-expense-'.$expense->id.'.pdf');
    }

    public function viewStaffAdvance(StaffAdvance $advance)
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => 'Staff Advance Salary',
            'rows' => $this->pdfRows('Staff Member', optional($advance->staff)->name, $advance, [
                'Month' => $advance->year_month,
                'Instruction' => $advance->instruction,
            ]),
        ]);

        return $pdf->stream('staff-advance-'.$advance->id.'.pdf');
    }
}

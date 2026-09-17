<?php

namespace App\Http\Controllers;

use App\Models\FoodCashDeposit;
use App\Models\HotelCashDeposit;
use App\Support\NumberToWords;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RevenueController extends Controller
{
    public function index()
    {
        $hotelDeposits = HotelCashDeposit::with('user')->latest('date')->latest('time')->get();
        $foodDeposits = FoodCashDeposit::with('user')->latest('date')->latest('time')->get();

        return view('revenue.index', compact('hotelDeposits', 'foodDeposits'));
    }

    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'time' => ['required'],
            'depositor' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function storeHotel(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);

        HotelCashDeposit::create($data);

        return redirect()->route('revenue.index')->with('success', 'Hotel cash deposit recorded.');
    }

    public function storeFood(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['user_id'] = Auth::id();
        $data['full_name'] = Auth::user()->name;
        $data['amount_in_words'] = NumberToWords::convert($data['amount']);

        FoodCashDeposit::create($data);

        return redirect()->route('revenue.index')->with('success', 'Food cash deposit recorded.');
    }

    public function destroyHotel(HotelCashDeposit $deposit)
    {
        if (! Auth::user()->canManage($deposit->user)) {
            return back()->with('error', 'You are not allowed to delete this record.');
        }
        $deposit->delete();

        return back()->with('success', 'Hotel deposit deleted.');
    }

    public function destroyFood(FoodCashDeposit $deposit)
    {
        if (! Auth::user()->canManage($deposit->user)) {
            return back()->with('error', 'You are not allowed to delete this record.');
        }
        $deposit->delete();

        return back()->with('success', 'Food deposit deleted.');
    }

    public function viewHotel(HotelCashDeposit $deposit)
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => 'Hotel Revenue Receipt',
            'record' => $deposit,
            'rows' => [
                'Date' => optional($deposit->date)->format('d-m-Y'),
                'Time' => substr((string) $deposit->time, 0, 5),
                'Recorded By' => $deposit->full_name,
                'Depositor' => $deposit->depositor,
                'Amount' => '₹'.number_format($deposit->amount, 2),
                'Amount in Words' => $deposit->amount_in_words,
            ],
        ]);

        return $pdf->stream('hotel-revenue-'.$deposit->id.'.pdf');
    }

    public function viewFood(FoodCashDeposit $deposit)
    {
        $pdf = Pdf::loadView('pdf.receipt', [
            'title' => 'Food Revenue Receipt',
            'record' => $deposit,
            'rows' => [
                'Date' => optional($deposit->date)->format('d-m-Y'),
                'Time' => substr((string) $deposit->time, 0, 5),
                'Recorded By' => $deposit->full_name,
                'Depositor' => $deposit->depositor,
                'Amount' => '₹'.number_format($deposit->amount, 2),
                'Amount in Words' => $deposit->amount_in_words,
            ],
        ]);

        return $pdf->stream('food-revenue-'.$deposit->id.'.pdf');
    }
}

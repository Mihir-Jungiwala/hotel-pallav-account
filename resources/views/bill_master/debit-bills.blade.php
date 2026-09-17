@extends('layouts.app')
@section('title', 'Bill Master — Debit Bills')
@section('content')

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Bill No.</th><th>Guest</th><th>Hotel Bal.</th><th>Food Bal.</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($bills as $b)
                <tr>
                    <td class="fw-semibold">{{ $b->bill_number }}</td>
                    <td>{{ $b->guest_name }}</td>
                    <td>₹{{ number_format($b->balance_hotel_amount, 2) }}</td>
                    <td>₹{{ number_format($b->balance_food_amount, 2) }}</td>
                    <td class="text-end">
                        @if(! $b->hasDebitBillRecorded())
                            <button class="btn btn-sm btn-p" data-bs-toggle="modal" data-bs-target="#settleBill{{ $b->id }}">Settle</button>
                        @else
                            <form method="POST" action="{{ route('bill-master.debit-bills.destroy', $b) }}" class="d-inline" onsubmit="return confirm('Reverse this settlement?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary">Reverse Settlement</button></form>
                        @endif
                    </td>
                </tr>

                <div class="modal fade" id="settleBill{{ $b->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('bill-master.debit-bills.store', $b) }}">@csrf
                        <div class="modal-header"><h5 class="modal-title">Settle Debit Bill {{ $b->bill_number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <p class="text-muted">Outstanding: Hotel ₹{{ number_format($b->balance_hotel_amount,2) }} &middot; Food ₹{{ number_format($b->balance_food_amount,2) }}</p>
                            @foreach(range(0,4) as $i)
                                @php $suffix = $i === 0 ? '' : "_$i"; @endphp
                                <div class="row g-2 mb-2 align-items-end">
                                    <div class="col-12 small fw-semibold text-muted">Installment {{ $i + 1 }}</div>
                                    <div class="col-md-3"><input type="date" name="debit_bill_date{{ $suffix }}" class="form-control" placeholder="Date"></div>
                                    <div class="col-md-3"><input type="number" step="0.01" name="debit_hotel_amount{{ $suffix }}" class="form-control" placeholder="Hotel Amount"></div>
                                    <div class="col-md-2"><input name="debit_hotel_mode{{ $suffix }}" class="form-control" placeholder="Hotel Mode"></div>
                                    <div class="col-md-2"><input type="number" step="0.01" name="debit_food_amount{{ $suffix }}" class="form-control" placeholder="Food Amount"></div>
                                    <div class="col-md-2"><input name="debit_food_mode{{ $suffix }}" class="form-control" placeholder="Food Mode"></div>
                                </div>
                            @endforeach
                            <div class="row g-3 mt-2">
                                <div class="col-md-6"><label class="form-label">Reference Name</label><input name="debit_reference_name" class="form-control"></div>
                                <div class="col-md-6"><label class="form-label">Reference Mobile</label><input name="debit_reference_mobile_number" class="form-control"></div>
                                <div class="col-12"><label class="form-label">Instruction</label><textarea name="debit_instruction" class="form-control"></textarea></div>
                            </div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-p">Record Settlement</button></div>
                    </form>
                </div></div></div>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No debit bills pending.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@extends('layouts.app')
@section('title', 'Bill Master — Bills')
@section('content')

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addBill"><i class="bi bi-plus-lg"></i> New Bill</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Bill No.</th><th>Guest</th><th>Hotel Total</th><th>Food Total</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($bills as $b)
                <tr>
                    <td class="fw-semibold">{{ $b->bill_number }}</td>
                    <td>{{ $b->guest_name }}</td>
                    <td>₹{{ number_format($b->total_hotel_amount, 2) }}</td>
                    <td>₹{{ number_format($b->total_food_amount, 2) }}</td>
                    <td class="text-end">
                        @if(! $b->hasDebitBillRecorded())
                            <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editBill{{ $b->id }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('bill-master.bills.destroy', $b) }}" class="d-inline" onsubmit="return confirm('Delete this bill?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        @else
                            <span class="badge-p px-2 py-1 rounded-pill">Debit Bill Recorded</span>
                        @endif
                    </td>
                </tr>

                <div class="modal fade" id="editBill{{ $b->id }}" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                    <form method="POST" action="{{ route('bill-master.bills.update', $b) }}" enctype="multipart/form-data">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Edit Bill {{ $b->bill_number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">@include('bill_master._bill-fields', ['bill' => $b, 'companies' => $companies, 'advances' => $advances])</div>
                        <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                    </form>
                </div></div></div>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No bills created yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addBill" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form method="POST" action="{{ route('bill-master.bills.store') }}" enctype="multipart/form-data">@csrf
        <div class="modal-header"><h5 class="modal-title">New Bill</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('bill_master._bill-fields', ['bill' => null, 'companies' => $companies, 'advances' => $advances])</div>
        <div class="modal-footer"><button class="btn btn-p">Create Bill</button></div>
    </form>
</div></div></div>
@endsection

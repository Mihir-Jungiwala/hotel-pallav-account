@extends('layouts.app')
@section('title', 'Bill Master — Advances')
@section('content')

<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addAdvance"><i class="bi bi-plus-lg"></i> New Advance</button>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Receipt No.</th><th>Guest</th><th>Hotel Bal.</th><th>Food Bal.</th><th>Refunded</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($advances as $a)
                <tr>
                    <td class="fw-semibold">{{ $a->receipt_number }}</td>
                    <td>{{ $a->guest_name }}</td>
                    <td>₹{{ number_format($a->hotel_balance, 2) }}</td>
                    <td>₹{{ number_format($a->food_balance, 2) }}</td>
                    <td>{{ $a->isRefunded() ? 'Yes' : 'No' }}</td>
                    <td class="text-end">
                        @if($a->isUnused() && ! $a->isRefunded())
                            <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editAdvance{{ $a->id }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('bill-master.advances.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Delete this advance?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        @endif
                        @if(! $a->isRefunded())
                            <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#refundAdvance{{ $a->id }}"><i class="bi bi-arrow-return-left"></i> Refund</button>
                        @else
                            <form method="POST" action="{{ route('bill-master.advances.refund.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Reverse this refund?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-secondary">Undo Refund</button></form>
                        @endif
                    </td>
                </tr>

                <div class="modal fade" id="editAdvance{{ $a->id }}" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
                    <form method="POST" action="{{ route('bill-master.advances.update', $a) }}">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Edit Advance {{ $a->receipt_number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">@include('bill_master._advance-fields', ['advance' => $a, 'companies' => $companies])</div>
                        <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                    </form>
                </div></div></div>

                <div class="modal fade" id="refundAdvance{{ $a->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <form method="POST" action="{{ route('bill-master.advances.refund', $a) }}">@csrf
                        <div class="modal-header"><h5 class="modal-title">Refund {{ $a->receipt_number }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body"><div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Hotel Refund Amount</label><input type="number" step="0.01" name="hotel_refund_amount" class="form-control" max="{{ $a->hotel_balance }}"></div>
                            <div class="col-md-6"><label class="form-label">Food Refund Amount</label><input type="number" step="0.01" name="food_refund_amount" class="form-control" max="{{ $a->food_balance }}"></div>
                            <div class="col-md-6"><label class="form-label">Hotel Refund Mode</label><input name="hotel_refund_mode" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Food Refund Mode</label><input name="food_refund_mode" class="form-control"></div>
                            <div class="col-md-6"><label class="form-label">Refund Date</label><input type="date" name="refund_payment_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Guest Name</label><input name="refund_guest_name" class="form-control" value="{{ $a->guest_name }}"></div>
                            <div class="col-md-6"><label class="form-label">Mobile Number</label><input name="refund_mobile_number" class="form-control"></div>
                            <div class="col-12"><label class="form-label">Instruction</label><textarea name="refund_instruction" class="form-control"></textarea></div>
                        </div></div>
                        <div class="modal-footer"><button class="btn btn-p">Process Refund</button></div>
                    </form>
                </div></div></div>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No advances recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addAdvance" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('bill-master.advances.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">New Advance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('bill_master._advance-fields', ['advance' => null, 'companies' => $companies])</div>
        <div class="modal-footer"><button class="btn btn-p">Create Advance</button></div>
    </form>
</div></div></div>
@endsection

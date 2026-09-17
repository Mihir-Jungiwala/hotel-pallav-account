@extends('layouts.app')
@section('title', 'Revenue')
@section('content')

<div class="d-flex justify-content-end gap-2 mb-3">
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addHotelDeposit"><i class="bi bi-plus-lg"></i> Hotel Deposit</button>
    <button class="btn btn-outline-p" data-bs-toggle="modal" data-bs-target="#addFoodDeposit"><i class="bi bi-plus-lg"></i> Food Deposit</button>
</div>

<div class="card mb-4">
    <div class="card-header">Hotel Cash Deposits</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Depositor</th><th>Amount</th><th>Recorded By</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($hotelDeposits as $d)
                <tr>
                    <td>{{ optional($d->date)->format('d-m-Y') }} {{ $d->time }}</td>
                    <td>{{ $d->depositor }}</td>
                    <td>₹{{ number_format($d->amount, 2) }}</td>
                    <td>{{ $d->full_name }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('revenue.hotel.view', $d) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                        <form method="POST" action="{{ route('revenue.hotel.destroy', $d) }}" class="d-inline" onsubmit="return confirm('Delete this deposit?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No hotel deposits yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Food Cash Deposits</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Date</th><th>Depositor</th><th>Amount</th><th>Recorded By</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
            @forelse($foodDeposits as $d)
                <tr>
                    <td>{{ optional($d->date)->format('d-m-Y') }} {{ $d->time }}</td>
                    <td>{{ $d->depositor }}</td>
                    <td>₹{{ number_format($d->amount, 2) }}</td>
                    <td>{{ $d->full_name }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('revenue.food.view', $d) }}" target="_blank"><i class="bi bi-file-pdf"></i></a>
                        <form method="POST" action="{{ route('revenue.food.destroy', $d) }}" class="d-inline" onsubmit="return confirm('Delete this deposit?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-4">No food deposits yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach(['hotel' => 'addHotelDeposit', 'food' => 'addFoodDeposit'] as $type => $modalId)
<div class="modal fade" id="{{ $modalId }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route("revenue.$type.store") }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title">{{ ucfirst($type) }} Cash Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ date('H:i') }}" required></div>
                        <div class="col-12"><label class="form-label">Depositor</label><input name="depositor" class="form-control" required></div>
                        <div class="col-12"><label class="form-label">Amount</label><input type="number" step="0.01" name="amount" class="form-control" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button class="btn btn-p">Save</button></div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection

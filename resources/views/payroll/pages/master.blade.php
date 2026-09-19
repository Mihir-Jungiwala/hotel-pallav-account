{{-- Payroll Master. The scope decides everything: with no company selected
     what is saved here applies to every company, with one selected it applies
     to that company only. The page says which, at the top and on every save. --}}
@extends('payroll.layout', [
    'title' => 'Payroll Master',
    'subtitle' => $company
        ? 'Changes here apply to '.$company->name.' only.'
        : 'Changes here apply to every company. Choose a company to keep something for that company alone.',
])

@php
    $isEmail = $definition['type'] === 'email';
    $valueLabel = $definition['value'];
    $scopeName = $company?->name ?? 'All companies';
@endphp

@section('page-actions')
    @unless($fixed)
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addMasterItem">
        <i class="bi bi-plus-lg"></i> Add
    </button>
    @endunless
@endsection

@section('page')

{{-- Scope: the one control that changes who a change reaches --}}
<div class="master-scope {{ $company ? 'is-company' : 'is-all' }}">
    <span class="ms-icon"><i class="bi {{ $company ? 'bi-building' : 'bi-globe2' }}"></i></span>
    <span class="ms-text">
        <span class="ms-label">Working on</span>
        <span class="ms-name">{{ $scopeName }}</span>
    </span>
    <form method="GET" action="{{ route('payroll.master.index') }}" class="ms-form">
        <input type="hidden" name="list" value="{{ $key }}">
        <select name="scope" class="form-select" aria-label="Change scope" onchange="this.form.submit()">
            <option value="all" @selected(! $company)>All companies</option>
            @foreach($companies as $option)
                <option value="{{ $option->id }}" @selected($company && $company->id === $option->id)>{{ $option->name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="master-layout master-tabs">
    <aside class="master-rail">
        @foreach($lists as $listKey => $list)
            <a href="{{ route('payroll.master.index', ['list' => $listKey]) }}"
               class="mr-item {{ $listKey === $key ? 'active' : '' }}">
                <span class="mr-icon"><i class="bi {{ $list['icon'] }}"></i></span>
                <span class="mr-body">
                    <span class="mr-name">{{ $list['name'] }}</span>
                    <span class="mr-hint">{{ \App\Support\PayrollMasters::isFixed($listKey) ? 'Built in' : $counts[$listKey].' for '.($company ? 'this company' : 'all companies') }}</span>
                </span>
            </a>
        @endforeach
    </aside>

    <section class="master-panel">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi {{ $definition['icon'] }} me-1"></i> {{ $definition['name'] }}</span>
                <span class="text-muted" style="font-size:12.5px;">{{ $definition['description'] }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="col-sno">S.No.</th>
                            <th>Name</th>
                            @if($isEmail)<th>{{ $valueLabel }}</th>@endif
                            <th>Applies to</th>
                            <th>Status</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @php $n = 0; @endphp
                    @foreach($own as $item)
                        <tr>
                            <td class="col-sno">{{ ++$n }}</td>
                            <td class="fw-semibold">{{ $item->label }}</td>
                            @if($isEmail)<td>{{ $item->value }}</td>@endif
                            <td><span class="pill {{ $company ? 'pill-live' : 'pill-locked' }}">{{ $scopeName }}</span></td>
                            <td>
                                @if($item->is_active)
                                    <span class="pill pill-live"><span class="dot"></span> On</span>
                                @else
                                    <span class="pill pill-locked">Off</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editMasterItem{{ $item->id }}" title="Edit"><i class="bi bi-pencil-square"></i></button>
                                <form method="POST" action="{{ route('payroll.master.toggle', $item) }}" class="d-inline">@csrf
                                    <button class="btn-icon" title="{{ $item->is_active ? 'Switch off' : 'Switch on' }}"><i class="bi {{ $item->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i></button>
                                </form>
                                <form method="POST" action="{{ route('payroll.master.destroy', $item) }}" class="d-inline"
                                      data-confirm-title="Remove {{ $item->label }}?" data-confirm="It is taken out of this list for {{ $scopeName }}." data-confirm-label="Remove">
                                    @csrf @method('DELETE')
                                    <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach

                    {{-- Shared items, shown on a company page so its list is the whole picture --}}
                    @foreach($inherited as $item)
                        <tr class="row-inherited">
                            <td class="col-sno">{{ ++$n }}</td>
                            <td class="fw-semibold">{{ $item->label }}</td>
                            @if($isEmail)<td>{{ $item->value }}</td>@endif
                            <td><span class="pill pill-locked"><i class="bi bi-globe2"></i> All companies</span></td>
                            <td>
                                @if($item->is_active)
                                    <span class="pill pill-live"><span class="dot"></span> On</span>
                                @else
                                    <span class="pill pill-locked">Off</span>
                                @endif
                            </td>
                            <td class="text-end text-muted" style="font-size:12px;">Change under All companies</td>
                        </tr>
                    @endforeach

                    @if($fixed)
                        @foreach($builtIn as $label)
                            <tr>
                                <td class="col-sno">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $label }}</td>
                                <td><span class="pill pill-locked"><i class="bi bi-lock"></i> Built in</span></td>
                                <td><span class="pill pill-live"><span class="dot"></span> On</span></td>
                                <td class="text-end text-muted" style="font-size:12px;">Fixed</td>
                            </tr>
                        @endforeach
                    @elseif($own->isEmpty() && $inherited->isEmpty() && ! empty($builtIn))
                        @foreach($builtIn as $label)
                            <tr class="row-inherited">
                                <td class="col-sno">{{ $loop->iteration }}</td>
                                <td class="fw-semibold">{{ $label }}</td>
                                @if($isEmail)<td></td>@endif
                                <td><span class="pill pill-locked"><i class="bi bi-lock"></i> Built in</span></td>
                                <td><span class="pill pill-live"><span class="dot"></span> On</span></td>
                                <td class="text-end text-muted" style="font-size:12px;">Used until you add your own</td>
                            </tr>
                        @endforeach
                    @elseif($own->isEmpty() && $inherited->isEmpty())
                        <tr>
                            <td colspan="{{ $isEmail ? 6 : 5 }}">
                                <div class="empty-state">
                                    <div class="es-icon"><i class="bi {{ $definition['icon'] }}"></i></div>
                                    <div class="es-title">Nothing here yet</div>
                                    <div class="es-text">Add the first one for {{ $scopeName }}.</div>
                                </div>
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

@foreach($own as $item)
    <div class="modal fade pay-form-modal" id="editMasterItem{{ $item->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.master.update', $item) }}">@csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">{{ $item->label }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('payroll.partials._master-fields', compact('item', 'isEmail', 'valueLabel'))</div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>
@endforeach

<div class="modal fade pay-form-modal" id="addMasterItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.master.store', $key) }}">@csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">For {{ $scopeName }}</div>
                    <h5 class="modal-title">Add to {{ $definition['name'] }}</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">@include('payroll.partials._master-fields', ['item' => null, 'isEmail' => $isEmail, 'valueLabel' => $valueLabel])</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Add</button>
            </div>
        </form>
    </div></div>
</div>

@if($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var m = document.getElementById('addMasterItem');
            if (m && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(m).show(); }
        });
    </script>
@endif
@endsection

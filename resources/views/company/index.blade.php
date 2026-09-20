@extends('layouts.app')
@section('title', 'Company Profiles')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/cashbook.css') }}?v=12">
<link rel="stylesheet" href="{{ asset('assets/company.css') }}?v=7">
@endpush

@section('content')
@php
    $rate = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
    $rateLabels = ['discount_percentage' => 'Discount', 'gst_percentage' => 'GST', 'tcs_percentage' => 'TCS', 'tds_percentage' => 'TDS'];
    $initials = fn ($name) => collect(preg_split('/\s+/', trim($name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">{{ now()->format('l, d F Y') }}</div>
        <h2 class="pms-title">Company Profiles</h2>
        <p class="pms-sub">The companies you bill, with their tax details and the people to contact.</p>
    </div>
    <div class="co-head-actions">
        <form method="GET" action="{{ route('company.index') }}" class="cb-search" role="search" data-cb-search>
            @if(request('per'))<input type="hidden" name="per" value="{{ request('per') }}">@endif
            <div class="search-field smart">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="{{ $q }}" class="form-control" autocomplete="off"
                       placeholder="Search companies&hellip;" aria-label="Search companies" title='Tips: several words must all match, "exact phrase"'>
                @if($q !== '')
                    <a class="search-clear" href="{{ route('company.index', array_filter(['per' => request('per')])) }}" title="Clear search" aria-label="Clear search"><i class="bi bi-x-lg"></i></a>
                @endif
            </div>
        </form>
        <button type="button" class="btn btn-p" data-bs-toggle="modal" data-bs-target="#companyModal" data-write-only>
            <i class="bi bi-building-add"></i> Add Company
        </button>
    </div>
</div>

<div class="kpi-grid">
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon month"><i class="bi bi-buildings"></i></span><span class="kpi-label">Companies</span></div>
        <div class="kpi-value">{{ $stats['total'] }}</div>
        <div class="kpi-foot"><span class="kpi-chip">On record</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon in"><i class="bi bi-receipt"></i></span><span class="kpi-label">With a GST number</span></div>
        <div class="kpi-value">{{ $stats['withGst'] }}</div>
        <div class="kpi-foot"><span class="kpi-chip">{{ $stats['total'] - $stats['withGst'] }} without</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon due"><i class="bi bi-people"></i></span><span class="kpi-label">People on file</span></div>
        <div class="kpi-value">{{ $stats['contacts'] }}</div>
        <div class="kpi-foot"><span class="kpi-chip">Across all companies</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon out"><i class="bi bi-calendar3"></i></span><span class="kpi-label">Added this month</span></div>
        <div class="kpi-value">{{ $stats['month'] }}</div>
        <div class="kpi-foot"><span class="kpi-chip">{{ now()->format('F Y') }}</span></div>
    </div>
</div>

@if($companies->isEmpty())
    <div class="card reveal">
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-buildings"></i></div>
            <div class="es-title">{{ $q !== '' ? 'No companies match "'.$q.'"' : 'No companies added yet' }}</div>
            <div class="es-text">Add a company to keep its GST number, billing rates and contacts in one place.</div>
            <button type="button" class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#companyModal" data-write-only><i class="bi bi-building-add"></i> Add Company</button>
        </div>
    </div>
@else
    <div class="card cb-table-card reveal">
        <div class="table-responsive cb-table-wrap">
            <table class="table cb-table mb-0">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Billing rates</th>
                        <th>People</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($companies as $company)
                    @php
                        $people = collect($company->contacts ?? []);
                        $rates = collect($rateLabels)->filter(fn ($label, $field) => $company->{$field} !== null && (float) $company->{$field} > 0);
                    @endphp
                    <tr class="cb-row co-row">
                        <td class="cb-c-name" data-label="Company">
                            <div class="co-id">
                                <span class="co-avatar" aria-hidden="true">{{ $initials($company->name) }}</span>
                                <div class="co-id-text">
                                    <button type="button" class="co-name" data-bs-toggle="modal" data-bs-target="#companyView" data-company-id="{{ $company->id }}">{{ $company->name }}</button>
                                    @if($company->gst_number)<span class="cb-chip kind">GST {{ $company->gst_number }}</span>@else<span class="co-sub">No GST number</span>@endif
                                    <span class="co-sub">Added {{ $company->created_at?->format('d M Y') }}</span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Location">
                            @if($company->address)<div class="co-line">{{ $company->address }}</div>@endif
                            @if($company->pincode || $company->country)<div class="co-sub">{{ collect([$company->pincode, $company->country])->filter()->implode(' · ') }}</div>@endif
                            @unless($company->address || $company->pincode || $company->country)<span class="text-muted">-</span>@endunless
                        </td>
                        <td data-label="Contact">
                            @if($company->email)<div class="co-line"><i class="bi bi-envelope"></i> {{ $company->email }}</div>@endif
                            @if($company->mobile_number)<div class="co-line"><i class="bi bi-phone"></i> {{ $company->mobile_number }}</div>@endif
                            @if($company->phone_number)<div class="co-line"><i class="bi bi-telephone"></i> {{ $company->phone_number }}</div>@endif
                            @unless($company->email || $company->mobile_number || $company->phone_number)<span class="text-muted">-</span>@endunless
                        </td>
                        <td data-label="Billing rates">
                            @forelse($rates as $field => $label)<span class="cb-chip">{{ $label }} {{ $rate($company->{$field}) }}</span> @empty<span class="text-muted">-</span>@endforelse
                        </td>
                        <td data-label="People">
                            @forelse($people->take(2) as $person)
                                <div class="co-person-cell"><i class="bi bi-person"></i><div><strong>{{ $person['name'] ?: $person['email'] }}</strong>@if(! empty($person['role']))<span class="co-role">{{ $person['role'] }}</span>@endif</div></div>
                            @empty<span class="text-muted">-</span>@endforelse
                            @if($people->count() > 2)<span class="cb-chip">+{{ $people->count() - 2 }} more</span>@endif
                        </td>
                        <td class="cb-c-actions">
                            <button type="button" class="cb-icon-btn" data-bs-toggle="modal" data-bs-target="#companyView" data-company-id="{{ $company->id }}" title="View details" aria-label="View {{ $company->name }}"><i class="bi bi-eye"></i></button>
                            <a class="cb-icon-btn" href="{{ route('company.view', $company) }}" target="_blank" title="Profile sheet (PDF)" aria-label="Open the PDF profile for {{ $company->name }}"><i class="bi bi-file-earmark-pdf"></i></a>
                            <button type="button" class="cb-icon-btn" data-bs-toggle="modal" data-bs-target="#companyModal" data-company-id="{{ $company->id }}" data-open-record title="Edit" aria-label="Edit {{ $company->name }}"><i class="bi bi-pencil-square"></i></button>
                            <form method="POST" action="{{ route('company.destroy', $company) }}" class="d-inline" data-confirm-title="Delete {{ $company->name }}?" data-confirm="This company profile will be removed and cannot be restored.">
                                @csrf @method('DELETE')
                                <button class="cb-icon-btn danger" title="Delete" aria-label="Delete {{ $company->name }}"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="cb-pager">@include('partials._server-pager', ['paginator' => $companies])</div>
    </div>
@endif

{{-- View: everything about one company, read only --}}
<div class="modal fade pay-form-modal cb-modal co-modal co-view" id="companyView" tabindex="-1" aria-labelledby="companyViewTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header">
            <div class="co-id">
                <span class="co-avatar lg" data-v="initials" aria-hidden="true"></span>
                <div class="co-id-text">
                    <h5 class="modal-title" id="companyViewTitle" data-v="name"></h5>
                    <div class="co-view-sub" data-v="sub"></div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="co-view-rates" data-v="rates"></div>

            <div class="co-view-grid">
                <section class="co-view-card">
                    <h6><i class="bi bi-telephone"></i> Contact</h6>
                    <dl data-v="contact"></dl>
                </section>
                <section class="co-view-card">
                    <h6><i class="bi bi-geo-alt"></i> Location</h6>
                    <dl data-v="location"></dl>
                </section>
            </div>

            <section class="co-view-card" data-v-section="instruction">
                <h6><i class="bi bi-chat-left-text"></i> Instruction</h6>
                <p class="mb-0" data-v="instruction"></p>
            </section>

            <section class="co-view-card">
                <h6><i class="bi bi-people"></i> People <span class="co-count" data-v="peopleCount">0</span></h6>
                <div class="co-view-people" data-v="people"></div>
            </section>
        </div>
        <div class="modal-footer">
            <form method="POST" action="#" class="me-auto" data-view-delete data-confirm-title="Delete company?" data-confirm="This company profile will be removed and cannot be restored.">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Delete</button>
            </form>
            <a class="btn btn-outline-p" href="#" target="_blank" data-view-pdf><i class="bi bi-file-earmark-pdf"></i> PDF</a>
            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-p" data-view-edit data-write-only><i class="bi bi-pencil-square"></i> Edit</button>
        </div>
    </div></div>
</div>

{{-- Add / edit: one pop-up, filled from the JSON below --}}
<div class="modal fade pay-form-modal cb-modal co-modal" id="companyModal" tabindex="-1" aria-labelledby="companyModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('company.store') }}" id="companyForm">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled data-method>
            <input type="hidden" name="_form" value="company">
            <input type="hidden" name="_company_id" value="" data-record-id>

            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow" data-subtitle>Company Profiles &middot; New</div>
                    <h5 class="modal-title" id="companyModalTitle" data-title>Add Company</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">@include('company._fields', ['roles' => $roles])</div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" data-delete-btn hidden><i class="bi bi-trash"></i> Delete</button>
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-p" data-busy-label="Saving…" data-save-label><i class="bi bi-check2"></i> Create Company</button>
            </div>
        </form>
        <form method="POST" action="#" id="companyDeleteForm" hidden data-confirm-title="Delete company?" data-confirm="This company profile will be removed and cannot be restored.">
            @csrf @method('DELETE')
        </form>
    </div></div>
</div>

<script type="application/json" id="companyData">{!! json_encode(['blank' => $blank, 'records' => $forms, 'reopen' => $reopen, 'roles' => $roles, 'rates' => $rateLabels], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

@push('scripts')
<script src="{{ asset('assets/company.js') }}?v=5"></script>
@endpush
@endsection

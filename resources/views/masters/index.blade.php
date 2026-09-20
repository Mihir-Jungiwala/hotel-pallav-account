@extends('layouts.app')
@section('title', 'Master Data')

@section('content')
@php use App\Models\OptionSet; @endphp

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">System</div>
        <h2 class="pms-title">Master Data</h2>
        <p class="pms-sub">Every dropdown in the app reads from these lists. Change one here and it changes on every form.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#newSet"><i class="bi bi-plus-lg"></i> New List</button>
    </div>
</div>

<div class="master-layout">
    <aside class="master-rail reveal">
        @foreach($sets as $set)
            <a href="{{ route('masters.index', ['set' => $set->key]) }}"
               class="mr-item {{ $current && $current->is($set) ? 'active' : '' }}">
                <span class="mr-icon"><i class="bi {{ $set->icon }}"></i></span>
                <span class="mr-body">
                    <span class="mr-name">{{ $set->name }}</span>
                    <span class="mr-hint">{{ $set->items->where('is_active', true)->count() }} in use</span>
                </span>
                @if($set->is_system)<i class="bi bi-shield-lock mr-lock" title="Built in"></i>@endif
            </a>
        @endforeach
    </aside>

    <section class="master-panel">
        @if($current)
            @php
                $nameOnly = $current->isNameOnly();
                $rows = $nameOnly
                    ? $current->items->sortBy(fn ($row) => mb_strtolower($row->label), SORT_NATURAL)->values()
                    : $current->items;
            @endphp
            <div class="card reveal">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span><i class="bi {{ $current->icon }} me-1"></i> {{ $current->name }}</span>
                    <div class="d-flex gap-2">
                        @unless($nameOnly)<button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editSet"><i class="bi bi-sliders"></i> List settings</button>@endunless
                        <button class="btn btn-sm btn-p" data-bs-toggle="modal" data-bs-target="#newItem"><i class="bi bi-plus-lg"></i> {{ $nameOnly ? 'Add name' : 'Add option' }}</button>
                    </div>
                </div>

                @unless($nameOnly)
                <div class="px-3 pt-3">
                    <div class="master-note">
                        <i class="bi bi-info-circle"></i>
                        <span>
                            {{ rtrim($current->description ?: 'Used on forms across the app', '.') }}.
                            Shown as <strong>{{ $current->inputLabel() }}</strong>.
                            Hiding an option keeps it on records that already use it.
                        </span>
                    </div>
                </div>
                @endunless

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                @unless($nameOnly)<th style="width:70px;">Order</th>@endunless
                                <th>{{ $nameOnly ? 'Name' : 'Option' }}</th>
                                @unless($nameOnly)<th>Saved value</th><th>Status</th>@endunless
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($rows as $item)
                            <tr>
                                @unless($nameOnly)
                                <td>
                                    <div class="order-cell">
                                        <form method="POST" action="{{ route('masters.items.move', $item) }}" data-no-busy="true">
                                            @csrf <input type="hidden" name="direction" value="up">
                                            <button class="btn-icon" title="Move up" @disabled($loop->first)><i class="bi bi-chevron-up"></i></button>
                                        </form>
                                        <form method="POST" action="{{ route('masters.items.move', $item) }}" data-no-busy="true">
                                            @csrf <input type="hidden" name="direction" value="down">
                                            <button class="btn-icon" title="Move down" @disabled($loop->last)><i class="bi bi-chevron-down"></i></button>
                                        </form>
                                    </div>
                                </td>
                                @endunless
                                <td>
                                    <span class="fw-semibold">{{ $item->label }}</span>
                                    @if($item->is_default)<span class="you-tag">default</span>@endif
                                </td>
                                @unless($nameOnly)
                                <td><code class="master-value">{{ $item->value }}</code></td>
                                <td>
                                    @if($item->is_active)
                                        <span class="pill pill-live"><span class="dot"></span> On forms</span>
                                    @else
                                        <span class="pill pill-locked">Hidden</span>
                                    @endif
                                </td>
                                @endunless
                                <td class="text-end text-nowrap">
                                    <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editItem{{ $item->id }}" data-open-record title="Open"><i class="bi bi-pencil-square"></i></button>
                                    @unless($nameOnly)
                                    <form method="POST" action="{{ route('masters.items.toggle', $item) }}" class="d-inline">
                                        @csrf
                                        <button class="btn-icon" title="{{ $item->is_active ? 'Hide from forms' : 'Show on forms' }}">
                                            <i class="bi {{ $item->is_active ? 'bi-eye-slash' : 'bi-eye' }}"></i>
                                        </button>
                                    </form>
                                    @endunless
                                    <form method="POST" action="{{ route('masters.items.destroy', $item) }}" class="d-inline"
                                          data-confirm-title="Remove {{ $item->label }}?" data-confirm="It leaves this list. Records that already use it keep it." data-confirm-label="Remove">
                                        @csrf @method('DELETE')
                                        <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>

                            <div class="modal fade" id="editItem{{ $item->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                <form method="POST" action="{{ route('masters.items.update', $item) }}">@csrf @method('PUT')
                                    <div class="modal-header">
                                        <h5 class="modal-title">{{ $item->label }}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        @include('masters._item-fields', ['item' => $item, 'nameOnly' => $nameOnly])
                                    </div>
                                    <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                                </form>
                            </div></div></div>
                        @empty
                            <tr>
                                <td colspan="{{ $nameOnly ? 2 : 5 }}">
                                    <div class="empty-state">
                                        <div class="es-icon"><i class="bi bi-list-ul"></i></div>
                                        <div class="es-title">Nothing in this list yet</div>
                                        <div class="es-text">{{ $nameOnly ? 'Add the first name and it appears on the form.' : 'Add the first option and it appears on every form that uses '.$current->name.'.' }}</div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="master-foot">
                @unless($nameOnly)<span><i class="bi bi-key"></i> Forms ask for this list by the key <code>{{ $current->key }}</code>.</span>@endunless
                @unless($current->is_system)
                    <form method="POST" action="{{ route('masters.sets.destroy', $current) }}"
                          data-confirm-title="Remove the whole {{ $current->name }} list?" data-confirm="Every option in it goes too." data-confirm-label="Remove list">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Delete list</button>
                    </form>
                @endunless
            </div>

            {{-- Add option --}}
            <div class="modal fade" id="newItem" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('masters.items.store', $current) }}">@csrf
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $nameOnly ? 'Add a name to ' : 'Add to ' }}{{ $current->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">@include('masters._item-fields', ['item' => null, 'nameOnly' => $nameOnly])</div>
                    <div class="modal-footer"><button class="btn btn-p">{{ $nameOnly ? 'Add Name' : 'Add Option' }}</button></div>
                </form>
            </div></div></div>

            {{-- List settings --}}
            <div class="modal fade" id="editSet" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('masters.sets.update', $current) }}">@csrf @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $current->name }} settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">@include('masters._set-fields', ['set' => $current])</div>
                    <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                </form>
            </div></div></div>
        @endif
    </section>
</div>

{{-- New list --}}
<div class="modal fade" id="newSet" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('masters.sets.store') }}">@csrf
        <div class="modal-header">
            <h5 class="modal-title">New list</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">@include('masters._set-fields', ['set' => null])</div>
        <div class="modal-footer"><button class="btn btn-p">Create List</button></div>
    </form>
</div></div></div>

@endsection

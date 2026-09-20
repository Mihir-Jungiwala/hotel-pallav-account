@extends('layouts.app')
@section('title', 'Roles and Permissions')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/access.css') }}?v=1">
@endpush

@section('content')
@php
    use App\Support\Permissions;

    // Keys are the module names the permissions are built from, so they have
    // to survive the grouping
    $grouped = collect($modules)->groupBy('group', preserveKeys: true);
    $iconChoices = ['bi-person', 'bi-person-gear', 'bi-shield-fill-check', 'bi-pencil-square', 'bi-eye',
        'bi-briefcase', 'bi-cash-coin', 'bi-people-fill', 'bi-clipboard-check', 'bi-headset', 'bi-key', 'bi-star-fill'];
    $accentChoices = ['#6D28D9', '#B45309', '#0F766E', '#64748B', '#BE123C', '#1D4ED8', '#047857', '#C2410C'];
@endphp

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">Access control</div>
        <h2 class="pms-title">Roles and Permissions</h2>
        <p class="pms-sub">Each role is given what it needs, and passes everything it has up to the role above it.</p>
    </div>
    <div class="ac-head-actions">
        <a class="btn btn-outline-p" href="{{ route('users.index') }}"><i class="bi bi-person-badge"></i> User Accounts</a>
        @if($canEdit)
            <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#roleModal" data-role-new><i class="bi bi-plus-lg"></i> New Role</button>
        @endif
    </div>
</div>

@unless($canEdit)
    <div class="ac-readonly reveal"><i class="bi bi-eye"></i> You can read this screen. Only an account with <strong>Edit Roles and Permissions</strong> can change it.</div>
@endunless

<div class="ac-layout">
    {{-- The ladder: highest rung at the top, dragged to reorder --}}
    <aside class="ac-ladder-wrap reveal">
        <div class="ac-side-head">
            <h3>The ladder</h3>
            <p>Highest at the top. A role gains everything below it.</p>
        </div>

        <ol class="ac-ladder" id="roleLadder" data-reorder="{{ route('access.reorder') }}" data-can-edit="{{ $canEdit ? '1' : '0' }}">
            @foreach($roles as $role)
                <li class="ac-rung {{ $role->id === $selected?->id ? 'current' : '' }} {{ $role->protectsTheSystem() ? 'pinned' : '' }}"
                    style="--role:{{ $role->accent }};" data-role-id="{{ $role->id }}" data-role-key="{{ $role->key }}"
                    draggable="{{ $canEdit && ! $role->protectsTheSystem() ? 'true' : 'false' }}">
                    <span class="ac-grip" aria-hidden="true"><i class="bi bi-grip-vertical"></i></span>
                    <a class="ac-rung-body" href="{{ route('access.index', ['role' => $role->key]) }}">
                        <span class="ac-rung-icon"><i class="bi {{ $role->icon }}"></i></span>
                        <span class="ac-rung-text">
                            <span class="ac-rung-name">{{ $role->name }}</span>
                            <span class="ac-rung-meta">
                                {{ $userCounts[$role->id] }} {{ Str::plural('account', $userCounts[$role->id]) }}
                                &middot; {{ count($role->effectivePermissions()) }} allowed
                            </span>
                        </span>
                    </a>
                    <span class="ac-rung-side">
                        @if($role->protectsTheSystem())
                            <span class="ac-pin" title="This role stays at the top"><i class="bi bi-pin-angle-fill"></i></span>
                        @elseif($canEdit)
                            <span class="ac-move">
                                <button type="button" data-move="up" title="Move up" aria-label="Move {{ $role->name }} up"><i class="bi bi-chevron-up"></i></button>
                                <button type="button" data-move="down" title="Move down" aria-label="Move {{ $role->name }} down"><i class="bi bi-chevron-down"></i></button>
                            </span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ol>

        <p class="ac-hint"><i class="bi bi-info-circle"></i> Drag a role, or use the arrows. The top rung is pinned: somebody has to keep the keys.</p>
    </aside>

    {{-- What the chosen role may do --}}
    <section class="ac-main reveal" id="rolePanel" data-role-id="{{ $selected?->id }}"
             data-permission-url="{{ $selected ? route('access.permission', $selected) : '' }}" style="--role:{{ $selected?->accent }};">
        @if($selected)
            <header class="ac-role-head">
                <span class="ac-role-icon"><i class="bi {{ $selected->icon }}"></i></span>
                <div class="ac-role-title">
                    <h3>{{ $selected->name }} @if($selected->is_system)<span class="ac-chip">Built in</span>@endif</h3>
                    <p>{{ $selected->description ?: 'No description yet.' }}</p>
                </div>
                @if($canEdit)
                    <div class="ac-role-tools">
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#roleModal"
                                data-role-edit="{{ json_encode([
                                    'id' => $selected->id, 'name' => $selected->name, 'description' => $selected->description,
                                    'icon' => $selected->icon, 'accent' => $selected->accent, 'inherits' => $selected->inherits,
                                    'system' => $selected->is_system, 'action' => route('access.update', $selected),
                                ]) }}"><i class="bi bi-pencil"></i> Edit</button>
                        @unless($selected->is_system)
                            <form method="POST" action="{{ route('access.destroy', $selected) }}" class="d-inline"
                                  data-confirm-title="Delete the {{ $selected->name }} role?"
                                  data-confirm="Accounts using it must be moved to another role first.">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        @endunless
                    </div>
                @endif
            </header>

            <div class="ac-summary">
                <div class="ac-sum"><span>Accounts</span><strong>{{ $userCounts[$selected->id] }}</strong></div>
                <div class="ac-sum"><span>Allowed in total</span><strong data-count-effective>{{ count($selected->effectivePermissions()) }}</strong></div>
                <div class="ac-sum"><span>Ticked here</span><strong data-count-own>{{ count($selected->ownPermissions()) }}</strong></div>
                <div class="ac-sum warn"><span>Sensitive</span><strong data-count-sensitive>{{ $selected->sensitiveCount() }}</strong></div>
            </div>

            @php $below = $selected->below()->first(); @endphp
            <div class="ac-inherit {{ $selected->inherits ? '' : 'off' }}">
                <i class="bi {{ $selected->inherits ? 'bi-arrow-down-up' : 'bi-slash-circle' }}"></i>
                @if($selected->inherits && $below)
                    Everything <strong>{{ $below->name }}</strong> may do, {{ $selected->name }} may do as well.
                @elseif($selected->inherits)
                    This is the bottom rung, so there is nothing below it to inherit.
                @else
                    Inheritance is off: this role has only what is ticked here.
                @endif
            </div>

            @foreach($grouped as $groupName => $groupModules)
                <h4 class="ac-group">{{ $groupName }}</h4>
                <div class="ac-grid" role="table">
                    <div class="ac-grid-head" role="row">
                        <span role="columnheader">Screen</span>
                        @foreach($actions as $key => $action)
                            <span role="columnheader" class="{{ ($action['sensitive'] ?? false) ? 'warn' : '' }}" title="{{ $action['hint'] }}">{{ $action['label'] }}</span>
                        @endforeach
                    </div>

                    @foreach($groupModules as $key => $module)
                        <div class="ac-row" role="row">
                            <span class="ac-module" role="cell">
                                <i class="bi {{ $module['icon'] }}"></i>
                                <span>
                                    <strong>{{ $module['label'] }}</strong>
                                    <small>{{ $module['about'] }}</small>
                                </span>
                            </span>
                            @foreach($actions as $action => $meta)
                                @php
                                    $permission = "{$key}.{$action}";
                                    $has = in_array($action, $module['actions'], true);
                                    $own = $has && in_array($permission, $selected->ownPermissions(), true);
                                    $source = $has ? $selected->sourceOf($permission) : null;
                                    $inherited = $source && ! $source->is($selected);
                                @endphp
                                <span class="ac-cell" role="cell">
                                    @if(! $has)
                                        <span class="ac-na" title="{{ $module['label'] }} has no {{ strtolower($meta['label']) }} action">&mdash;</span>
                                    @else
                                        <label class="ac-toggle {{ $inherited ? 'inherited' : '' }} {{ Permissions::isSensitive($permission) ? 'warn' : '' }}"
                                               title="{{ $inherited ? 'Comes from '.$source->name.'. Tick to give it to this role in its own right.' : $meta['hint'] }}">
                                            <input type="checkbox" data-permission="{{ $permission }}"
                                                   @checked($own) @disabled(! $canEdit)>
                                            <span class="ac-box">
                                                <i class="bi bi-check-lg"></i>
                                            </span>
                                            @if($inherited)<span class="ac-from">{{ $source->name }}</span>@endif
                                        </label>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach

            <h4 class="ac-group">Powers</h4>
            <div class="ac-abilities">
                @foreach($abilities as $permission => $ability)
                    @php
                        $own = in_array($permission, $selected->ownPermissions(), true);
                        $source = $selected->sourceOf($permission);
                        $inherited = $source && ! $source->is($selected);
                    @endphp
                    <label class="ac-ability {{ ($ability['sensitive'] ?? false) ? 'warn' : '' }} {{ $inherited ? 'inherited' : '' }}">
                        <input type="checkbox" data-permission="{{ $permission }}" @checked($own) @disabled(! $canEdit)>
                        <span class="ac-ability-body">
                            <span class="ac-ability-top">
                                <i class="bi {{ $ability['icon'] }}"></i>
                                <strong>{{ $ability['label'] }}</strong>
                                @if(($ability['sensitive'] ?? false))<span class="ac-warn-chip">Sensitive</span>@endif
                                @if($inherited)<span class="ac-from">from {{ $source->name }}</span>@endif
                            </span>
                            <small>{{ $ability['about'] }}</small>
                        </span>
                        <span class="ac-switch"><span></span></span>
                    </label>
                @endforeach
            </div>
        @else
            <div class="empty-state">
                <div class="es-icon"><i class="bi bi-diagram-3"></i></div>
                <div class="es-title">No roles yet</div>
            </div>
        @endif
    </section>
</div>

{{-- Create or edit a role --}}
<div class="modal fade pay-form-modal" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <form method="POST" action="{{ route('access.store') }}" id="roleForm">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled data-method>
            <input type="hidden" name="level" value="" data-level>
            <div class="modal-header">
                <div><div class="pms-eyebrow" data-form-eyebrow>Access control &middot; New</div>
                    <h5 class="modal-title" data-form-title>New Role</h5></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-person-badge"></i> What to call it</div></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label" for="role_name">Name<span class="req">*</span></label>
                            <input name="name" id="role_name" class="form-control" maxlength="40" required placeholder="Front Desk Supervisor">
                            <div class="form-text" data-system-note hidden>Built-in roles keep their name so the rest of the system can find them.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="role_desc">What this role is for<span class="opt">optional</span></label>
                            <textarea name="description" id="role_desc" class="form-control" rows="2" maxlength="500"
                                      placeholder="Runs the front desk: records the day's cash and hands over shifts."></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-palette"></i> How it looks</div></div>
                    <div class="ac-icon-pick" role="radiogroup" aria-label="Icon">
                        @foreach($iconChoices as $icon)
                            <label><input type="radio" name="icon" value="{{ $icon }}" @checked($loop->first)><span><i class="bi {{ $icon }}"></i></span></label>
                        @endforeach
                    </div>
                    <div class="ac-colour-pick" role="radiogroup" aria-label="Colour">
                        @foreach($accentChoices as $accent)
                            <label><input type="radio" name="accent" value="{{ $accent }}" @checked($loop->first)><span style="background:{{ $accent }};"></span></label>
                        @endforeach
                    </div>
                </div>

                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-arrow-down-up"></i> Inheritance</div></div>
                    <label class="ac-inherit-toggle">
                        <input type="checkbox" name="inherits" value="1" checked>
                        <span class="ac-switch"><span></span></span>
                        <span class="ac-ability-body">
                            <strong>Gains everything below it</strong>
                            <small>Off means this role has only what you tick for it, whatever sits underneath.</small>
                        </span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p" data-form-save>Create Role</button>
            </div>
        </form>
    </div></div>
</div>

@push('scripts')
<script src="{{ asset('assets/access.js') }}?v=1"></script>
@endpush
@endsection

{{-- What this one person may actually do, and why: their role, what that role
     inherits, and anything given to or taken from them alone. --}}
@php
    use App\Support\Access;
    use App\Support\Permissions;

    $role = $u->roleRecord();
    $overrides = Access::overrides($u);
    $effective = Access::effective($u);
    $modules = Permissions::modules();
    $abilities = Permissions::abilities();
    $canOverride = $canManage && auth()->user()->can('access.edit');
@endphp

<div class="up-head" style="--role:{{ $role?->accent ?? '#7C3AED' }};">
    <span class="up-role-icon"><i class="bi {{ $role?->icon ?? 'bi-person' }}"></i></span>
    <div>
        <strong>{{ $role?->name ?? $u->role }}</strong>
        <span>{{ $role?->description ?: 'No description yet.' }}</span>
    </div>
    <div class="up-head-counts">
        <span><strong>{{ count($effective) }}</strong> allowed</span>
        @if($overrides)<span class="warn"><strong>{{ count($overrides) }}</strong> just for them</span>@endif
    </div>
</div>

@if($overrides)
    <div class="up-overrides">
        <h6>Given to, or taken from, {{ Str::before($u->name, ' ') }} alone</h6>
        @foreach($overrides as $permission => $granted)
            <div class="up-override {{ $granted ? 'on' : 'off' }}">
                <i class="bi {{ $granted ? 'bi-plus-circle-fill' : 'bi-dash-circle-fill' }}"></i>
                <span>{{ Permissions::label($permission) }}</span>
                <small>{{ $granted ? 'added on top of their role' : 'taken away from their role' }}</small>
            </div>
        @endforeach
    </div>
@endif

<p class="up-legend">
    <span><i class="bi bi-circle-fill" style="color:var(--p600);"></i> From the role</span>
    <span><i class="bi bi-circle-half" style="color:var(--p400);"></i> Inherited from a lower role</span>
    <span><i class="bi bi-plus-circle-fill" style="color:#0F766E;"></i> Given to them</span>
    <span><i class="bi bi-dash-circle-fill" style="color:#B91C1C;"></i> Taken away</span>
</p>

<div class="up-list" data-user-permissions="{{ $canOverride ? route('users.permission', $u) : '' }}">
    @foreach(collect($modules)->groupBy('group', preserveKeys: true) as $groupName => $groupModules)
        <h6 class="up-group">{{ $groupName }}</h6>
        @foreach($groupModules as $key => $module)
            <div class="up-module">
                <div class="up-module-name"><i class="bi {{ $module['icon'] }}"></i> {{ $module['label'] }}</div>
                <div class="up-actions">
                    @foreach($module['actions'] as $action)
                        @php
                            $permission = "{$key}.{$action}";
                            $explained = Access::explain($u, $permission);
                        @endphp
                        <span class="up-action {{ $explained['allowed'] ? 'yes' : 'no' }} origin-{{ $explained['origin'] }}"
                              data-permission="{{ $permission }}"
                              title="{{ match($explained['origin']) {
                                  'role' => 'From their role, '.$explained['role']?->name,
                                  'inherited' => 'Inherited from '.$explained['role']?->name,
                                  'granted' => 'Given to them alone'.($explained['reason'] ? ': '.$explained['reason'] : ''),
                                  'revoked' => 'Taken away from them alone'.($explained['reason'] ? ': '.$explained['reason'] : ''),
                                  default => 'Not allowed',
                              } }}">
                            <i class="bi {{ match($explained['origin']) {
                                'role' => 'bi-circle-fill',
                                'inherited' => 'bi-circle-half',
                                'granted' => 'bi-plus-circle-fill',
                                'revoked' => 'bi-dash-circle-fill',
                                default => 'bi-circle',
                            } }}"></i>
                            {{ Permissions::ACTIONS[$action]['label'] }}
                            @if($canOverride)
                                <button type="button" class="up-flip" data-state="{{ $explained['allowed'] ? 'revoke' : 'grant' }}"
                                        title="{{ $explained['allowed'] ? 'Take this away from '.$u->name : 'Give this to '.$u->name }}"
                                        aria-label="{{ $explained['allowed'] ? 'Take away' : 'Give' }} {{ Permissions::label($permission) }}">
                                    <i class="bi {{ $explained['allowed'] ? 'bi-dash' : 'bi-plus' }}"></i>
                                </button>
                                @if(in_array($explained['origin'], ['granted', 'revoked'], true))
                                    <button type="button" class="up-flip clear" data-state="clear" title="Back to what the role says" aria-label="Reset to the role">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </button>
                                @endif
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endforeach

    <h6 class="up-group">Powers</h6>
    @foreach($abilities as $permission => $ability)
        @php $explained = Access::explain($u, $permission); @endphp
        <div class="up-module">
            <div class="up-module-name"><i class="bi {{ $ability['icon'] }}"></i> {{ $ability['label'] }}</div>
            <div class="up-actions">
                <span class="up-action {{ $explained['allowed'] ? 'yes' : 'no' }} origin-{{ $explained['origin'] }}" data-permission="{{ $permission }}">
                    <i class="bi {{ $explained['allowed'] ? 'bi-check-lg' : 'bi-x-lg' }}"></i>
                    {{ $explained['allowed'] ? 'Allowed' : 'Not allowed' }}
                    @if($explained['role'] && $explained['origin'] === 'inherited')<small>from {{ $explained['role']->name }}</small>@endif
                    @if($canOverride)
                        <button type="button" class="up-flip" data-state="{{ $explained['allowed'] ? 'revoke' : 'grant' }}"
                                aria-label="{{ $explained['allowed'] ? 'Take away' : 'Give' }} {{ $ability['label'] }}">
                            <i class="bi {{ $explained['allowed'] ? 'bi-dash' : 'bi-plus' }}"></i>
                        </button>
                    @endif
                </span>
            </div>
        </div>
    @endforeach
</div>

@if($canOverride)
    <p class="up-note"><i class="bi bi-info-circle"></i> Giving one person something on their own is meant to be rare. If several people need it, change their role instead.</p>
@endif

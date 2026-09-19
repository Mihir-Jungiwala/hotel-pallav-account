@php
    $daysInMonth = $month->daysInMonth();
    $locked = ! $month->isEditable();
    $statusMap = $statuses->mapWithKeys(fn ($s) => [strtoupper($s->shortcut_key) => [
        'color' => $s->color, 'percentage' => $s->attendance_percentage, 'name' => $s->name,
    ]]);
@endphp

@extends('payroll.layout', [
    'title' => 'Attendance Management',
    'subtitle' => 'Record the month day by day. Salary is calculated from what is entered here, so a month is locked once its salary has been generated.',
])

@section('page-actions')
    @unless($monthStart->isSameMonth(now()))
        <a class="btn btn-outline-p" href="{{ route('payroll.attendance.index') }}">
            <i class="bi bi-calendar-check"></i> This month
        </a>
    @endunless
@endsection

@section('page')

@if($statuses->isEmpty())
    <div class="card">
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-palette2"></i></div>
            <div class="es-title">No attendance statuses configured</div>
            <div class="es-text">Attendance is entered with shortcut keys like P, HD or WO, and each key carries the percentage of a day it pays. Set those up before recording a month.</div>
            <a class="btn btn-p mt-3" href="{{ route('payroll.attendance-status.index') }}">
                <i class="bi bi-arrow-right"></i> Go to Attendance Status
            </a>
        </div>
    </div>
@else

<div class="card attendance-shell">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar3"></i>
            <span>{{ strtoupper($monthStart->format('F Y')) }}</span>
            @if($locked)
                <span class="pill pill-locked"><i class="bi bi-lock-fill"></i> Locked</span>
            @elseif($month->isTemporarilyUnlocked())
                <span class="pill pill-unlocked"><i class="bi bi-unlock-fill"></i> Unlocked for correction</span>
            @else
                <span class="pill pill-live"><span class="dot"></span> Editable</span>
            @endif
        </div>

        @include('payroll.partials._month-nav', [
            'route' => 'payroll.attendance.index',
            'monthStart' => $monthStart,
        ])
    </div>

    <form method="POST" action="{{ route('payroll.attendance.save', $month) }}" id="attendanceForm">
        @csrf

        {{-- The legend sits above the grid so it stays readable while a whole
             month is being typed in. --}}
        <div class="legend-row">
            <div class="d-flex flex-wrap gap-2">
                @foreach($statuses as $status)
                    <span class="legend-chip">
                        <span class="lc-key" style="background:{{ $status->color }}">{{ strtoupper($status->shortcut_key) }}</span>
                        {{ $status->name }} ({{ $status->attendance_percentage }}%)
                    </span>
                @endforeach
            </div>
            @unless($locked)
                <div class="text-muted d-flex flex-wrap gap-3 mt-2" style="font-size:11.5px;">
                    <span><kbd>←</kbd><kbd>→</kbd><kbd>↑</kbd><kbd>↓</kbd> move</span>
                    <span><kbd>Enter</kbd> next employee</span>
                    <span><kbd>Ctrl</kbd>+<kbd>D</kbd> fill rest of row</span>
                </div>
            @endunless
        </div>

        <div class="attendance-scroll">
            <table class="attendance-grid w-100" data-attendance-grid data-statuses='@json($statusMap)'>
                <thead>
                    <tr>
                        <th class="sticky-col col-sr">S.No.</th>
                        <th class="sticky-col col-name">Name</th>
                        <th class="sticky-col col-desig">Designation</th>
                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $d = $monthStart->copy()->day($day);
                                $isSunday = $d->isSunday();
                                // A new week starts on Monday - mark it to break the month into weeks
                                $weekStart = $d->isMonday() && $day > 1;
                            @endphp
                            <th class="day-head {{ $isSunday ? 'is-sunday' : '' }} {{ $weekStart ? 'week-start' : '' }} {{ $d->isToday() ? 'is-today' : '' }} {{ $d->isFuture() ? 'is-future' : '' }}">
                                <span class="dh-num">{{ $day }}</span>
                                <span class="dh-dow">{{ $d->format('D') }}</span>
                            </th>
                        @endfor
                        <th class="text-center">0%</th>
                        <th class="text-center">25%</th>
                        <th class="text-center">50%</th>
                        <th class="text-center">75%</th>
                        <th class="text-center">100%</th>
                        <th class="text-center">OT<br><span style="font-weight:600;opacity:.7;">hrs</span></th>
                        <th class="text-center">Total<br><span style="font-weight:600;opacity:.7;">days</span></th>
                    </tr>
                </thead>
                <tbody>
                @forelse($employees as $i => $employee)
                    @php $employeeEntries = $entries[$employee->id] ?? collect(); @endphp
                    <tr class="employee-row" data-employee="{{ $employee->id }}">
                        <td rowspan="2" class="sticky-col col-sr text-muted">{{ $i + 1 }}</td>
                        <td rowspan="2" class="sticky-col col-name">
                            <div class="fw-semibold" style="font-size:12.5px;">{{ $employee->name }}</div>
                            <div class="text-muted" style="font-size:10.5px;">{{ $employee->employee_code }}</div>
                        </td>
                        <td rowspan="2" class="sticky-col col-desig text-muted" style="font-size:11.5px;">{{ $employee->designation }}</td>

                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $entry = $employeeEntries[$day] ?? null;
                                $key = $entry?->shortcut_key;
                                $d = $monthStart->copy()->day($day);
                                $cellClass = ($d->isSunday() ? ' is-sunday' : '').($d->isMonday() && $day > 1 ? ' week-start' : '')
                                    .($d->isToday() ? ' is-today' : '').($d->isFuture() ? ' is-future' : '');
                            @endphp
                            <td class="day-cell{{ $cellClass }}">
                                <input type="text" class="status-cell"
                                       name="attendance[{{ $employee->id }}][{{ $day }}]"
                                       data-employee="{{ $employee->id }}" data-day="{{ $day }}"
                                       maxlength="5" value="{{ $key }}" autocomplete="off"
                                       placeholder="·"
                                       aria-label="{{ $employee->name }}, day {{ $day }}"
                                       @disabled($locked)>
                            </td>
                        @endfor

                        <td class="summary-cell summary-0">0</td>
                        <td class="summary-cell summary-25">0</td>
                        <td class="summary-cell summary-50">0</td>
                        <td class="summary-cell summary-75">0</td>
                        <td class="summary-cell summary-100">0</td>
                        <td class="summary-cell summary-ot">0</td>
                        <td class="summary-cell total summary-total">0.00</td>
                    </tr>
                    <tr class="overtime-row" data-employee="{{ $employee->id }}">
                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $entry = $employeeEntries[$day] ?? null;
                                $d = $monthStart->copy()->day($day);
                                $cellClass = ($d->isSunday() ? ' is-sunday' : '').($d->isMonday() && $day > 1 ? ' week-start' : '');
                            @endphp
                            <td class="ot-cell{{ $cellClass }}">
                                <input type="number" step="0.5" min="0"
                                       name="overtime[{{ $employee->id }}][{{ $day }}]"
                                       data-employee="{{ $employee->id }}" data-day="{{ $day }}"
                                       value="{{ $entry && (float) $entry->overtime_hours > 0 ? (float) $entry->overtime_hours : '' }}"
                                       aria-label="{{ $employee->name }}, overtime day {{ $day }}"
                                       @disabled($locked)>
                            </td>
                        @endfor
                        <td colspan="7" class="text-muted ps-2" style="font-size:10.5px;">overtime hours</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $daysInMonth + 10 }}">
                            <div class="empty-state">
                                <div class="es-icon"><i class="bi bi-people"></i></div>
                                <div class="es-title">No active employees</div>
                                <div class="es-text">Add staff in Staff Management and they appear here, ready for attendance entry.</div>
                                <a class="btn btn-p mt-3" href="{{ route('payroll.staff.index') }}">
                                    <i class="bi bi-people"></i> Go to Staff Management
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="action-bar">
            @if($locked)
                <div class="lock-banner flex-grow-1">
                    <i class="bi bi-lock-fill"></i>
                    <span>
                        Salary was generated {{ optional($month->salary_generated_at)->format('d M Y, H:i') }}, so attendance is read-only.
                        @unless(auth()->user()->isAdmin()) Only an Admin can unlock it. @endunless
                    </span>
                </div>
                @if(auth()->user()->isAdmin())
                    <button type="submit" class="btn btn-outline-p"
                            formaction="{{ route('payroll.attendance.unlock', $month) }}" data-busy-label="Unlocking…">
                        <i class="bi bi-unlock"></i> Unlock Attendance
                    </button>
                @endif
            @else
                @php
                    $pct = $progress['required'] > 0 ? round($progress['filled'] / $progress['required'] * 100) : 0;
                    $monthOver = $month->isComplete();
                @endphp

                <div class="flex-grow-1">
                    @if(! $incompleteEmployees)
                        <div class="status-line ok">
                            <i class="bi bi-check-circle-fill"></i>
                            <span>All {{ $employees->count() }} employees complete - ready to generate salary.</span>
                        </div>
                    @elseif($monthOver)
                        {{-- The month is over, so missing days genuinely block salary --}}
                        <div class="status-line warn" title="{{ implode(', ', $incompleteEmployees) }}">
                            <i class="bi bi-exclamation-triangle-fill"></i>
                            <span>
                                {{ count($incompleteEmployees) }} of {{ $employees->count() }} employees are still incomplete -
                                finish these before generating salary.
                            </span>
                        </div>
                    @else
                        {{-- Month still running: this is normal progress, not a problem --}}
                        <div class="status-line">
                            <div class="progress-track" aria-hidden="true">
                                <span style="width: {{ max(2, $pct) }}%"></span>
                            </div>
                            <span>{{ number_format($progress['filled']) }} of {{ number_format($progress['required']) }} entries saved</span>
                        </div>
                    @endif
                </div>

                <button class="btn btn-p" data-busy-label="Saving…"><i class="bi bi-save"></i> Save</button>

                @if($month->isTemporarilyUnlocked())
                    <button type="submit" class="btn btn-outline-p"
                            formaction="{{ route('payroll.attendance.regenerate', $month) }}"
                            data-busy-label="Re-generating…"
                            data-confirm-click="This replaces the salary records, salary slips, reports and advance recoveries already generated for this month."
                            data-confirm-title="Re-generate salary for {{ $monthStart->format('F Y') }}?"
                            data-confirm-label="Re-generate"
                            data-confirm-tone="warning"
                            data-confirm-icon="bi-arrow-repeat">
                        <i class="bi bi-arrow-repeat"></i> Re-Generate Salary
                    </button>
                @elseif($month->isComplete())
                    <button type="submit" class="btn btn-outline-p"
                            formaction="{{ route('payroll.attendance.generate', $month) }}"
                            data-busy-label="Generating…"
                            data-confirm-click="Attendance for the month is locked afterwards. Changing it later needs an Admin to unlock the month and re-generate."
                            data-confirm-title="Generate salary for {{ $monthStart->format('F Y') }}?"
                            data-confirm-label="Generate Salary"
                            data-confirm-icon="bi-cash-stack">
                        <i class="bi bi-cash-stack"></i> Generate Salary
                    </button>
                @else
                    <button type="button" class="btn btn-outline-secondary" disabled
                            title="Available once {{ $monthStart->format('F') }} has ended">
                        <i class="bi bi-cash-stack"></i> Generate Salary
                    </button>
                @endif
            @endif
        </div>
    </form>
</div>
@endif

@endsection

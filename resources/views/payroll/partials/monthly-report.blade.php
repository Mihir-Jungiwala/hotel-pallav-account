<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-bar-chart"></i>
            <span>{{ strtoupper($monthStart->format('F Y')) }}</span>
            @if($rows->isNotEmpty())
                <span class="pill pill-locked"><i class="bi bi-lock-fill"></i> Processed</span>
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            @include('payroll.partials._month-nav', ['category' => 'monthly-report', 'monthStart' => $monthStart])

            @if($rows->isNotEmpty())
                <a class="btn btn-sm btn-p" target="_blank"
                   href="{{ route('payroll.report.monthly', ['year' => $monthStart->year, 'month' => $monthStart->month]) }}">
                    <i class="bi bi-download"></i> Download PDF
                </a>
            @endif
        </div>
    </div>

    @if($rows->isEmpty())
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-bar-chart"></i></div>
            <div class="es-title">No salary processed for {{ $monthStart->format('F Y') }}</div>
            <div class="es-text">
                @if($monthStart->isSameMonth(now()))
                    This month is still running. Salary can be generated from Attendance once it ends.
                @else
                    Generate salary for this month from Attendance, then the report appears here.
                @endif
            </div>
            <a class="btn btn-outline-p mt-3" href="{{ route('payroll.index', ['category' => 'attendance', 'year' => $monthStart->year, 'month' => $monthStart->month]) }}">
                <i class="bi bi-calendar3"></i> Open Attendance
            </a>
        </div>
    @endif
</div>

@if($grid && $rows->isNotEmpty())
    @php
        $daysInMonth = $grid['daysInMonth'];
        $start = $grid['start'];
        $statusMap = $grid['statuses'];
    @endphp

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-calendar3"></i> Attendance</div>

        <div class="legend-row">
            <div class="d-flex flex-wrap gap-2">
                @foreach($statusMap as $key => $meta)
                    <span class="legend-chip">
                        <span class="lc-key" style="background:{{ $meta['color'] }}">{{ $key }}</span>
                        {{ $meta['name'] }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="attendance-scroll">
            <table class="attendance-grid w-100">
                <thead>
                    <tr>
                        <th class="sticky-col col-sr">Sr.</th>
                        <th class="sticky-col col-name">Name</th>
                        <th class="sticky-col col-desig">Designation</th>
                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $d = $start->copy()->day($day);
                                $cls = ($d->isSunday() ? ' is-sunday' : '').($d->isMonday() && $day > 1 ? ' week-start' : '');
                            @endphp
                            <th class="day-head{{ $cls }}">
                                <span class="dh-num">{{ $day }}</span>
                                <span class="dh-dow">{{ $d->format('D') }}</span>
                            </th>
                        @endfor
                        <th class="text-center">Payable</th>
                        <th class="text-center">OT</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $i => $row)
                    @php $employeeEntries = $grid['entries'][$row->employee_id] ?? collect(); @endphp
                    <tr>
                        <td class="sticky-col col-sr text-muted">{{ $i + 1 }}</td>
                        <td class="sticky-col col-name">
                            <div class="fw-semibold" style="font-size:12.5px;">{{ $row->employee_name }}</div>
                            <div class="text-muted" style="font-size:10.5px;">{{ $row->employee_code }}</div>
                        </td>
                        <td class="sticky-col col-desig text-muted" style="font-size:11.5px;">{{ $row->designation }}</td>

                        @for($day = 1; $day <= $daysInMonth; $day++)
                            @php
                                $key = ($employeeEntries[$day] ?? null)?->shortcut_key;
                                $color = $key && isset($statusMap[strtoupper($key)]) ? $statusMap[strtoupper($key)]['color'] : null;
                                $d = $start->copy()->day($day);
                                $cls = ($d->isSunday() ? ' is-sunday' : '').($d->isMonday() && $day > 1 ? ' week-start' : '');
                            @endphp
                            <td class="day-cell{{ $cls }} text-center" style="padding:4px 2px;">
                                @if($key)
                                    <span style="display:inline-block; min-width:26px; padding:3px 5px; border-radius:6px;
                                                 background:{{ $color }}; color:#fff; font-weight:800; font-size:10.5px;">{{ $key }}</span>
                                @else
                                    <span class="text-muted" style="font-size:10px;">&middot;</span>
                                @endif
                            </td>
                        @endfor

                        <td class="summary-cell total">{{ number_format($row->total_payable_days, 2) }}</td>
                        <td class="summary-cell">{{ number_format($row->overtime_hours, 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

@if($rows->isNotEmpty())
<div class="card">
    <div class="card-header">Monthly Salary Report</div>
    @include('payroll.partials._report-table', ['emptyMessage' => ''])
</div>
@endif

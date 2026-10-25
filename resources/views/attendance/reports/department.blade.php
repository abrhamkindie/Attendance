@extends('layouts.master')

@section('content')
@php
// One department already selected means the user drilled in: open it.
$autoExpand = $groups->count() === 1;
$neverRecorded = $totals->never_recorded ?? 0;

// The status cards double as filters: each one links back to this page with a
// `status` parameter, while the card numbers stay on the whole scope.
$statusLabels = [
'present' => 'recorded attendance',
'absent' => 'absences',
'late' => 'late arrivals',
'on_duty' => 'on-duty punches',
];
$statusLabel = $statusLabels[$status] ?? null;

$tileUrl = function (string $value) use ($status) {
$query = request()->query();

if ($value === 'all' || $status === $value) {
unset($query['status']); // clicking the active card clears the filter
} else {
$query['status'] = $value;
}

return route('attendance.reports.department', $query) . '#attGroups';
};

$rateClass = function ($rate) {
if ($rate === null) {
return 'rate-none';
}
if ($rate >= 90) {
return 'rate-good';
}
if ($rate >= 75) {
return 'rate-warn';
}
return 'rate-bad';
};
@endphp

<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Attendance Reports</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Attendance Reports</li>
                        <li class="breadcrumb-item active">By department</li>
                    </ul>
                </div>
                <div class="col-auto d-flex flex-wrap gap-2">
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('attendance.reports.branch', request()->except('sort')) }}"
                            class="btn btn-outline-secondary">By branch</a>
                        <a href="{{ route('attendance.reports.department', request()->query()) }}"
                            class="btn btn-brand">By department</a>
                    </div>
                    <button type="button" class="btn btn-sm btn-light" onclick="window.print()">
                        <i class="fa fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>

        {{-- KPI tiles: one row, straight under the title. Every status card is a
             filter; the hours card is a plain figure. --}}
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 g-3 mb-3">
            <div class="col">
                <a class="att-tile-link" href="{{ $tileUrl('all') }}" title="Show every employee in scope">
                    <div class="att-tile{{ $neverRecorded > 0 ? ' att-tile-alert' : '' }}">
                        <span class="att-tile-label"><i class="fa fa-users"></i> People</span>
                        <span class="att-tile-value">{{ $totals->employees }}</span>
                        <span class="att-tile-sub{{ $neverRecorded > 0 ? ' is-alert' : '' }}">
                            @if ($neverRecorded > 0)
                            {{ $neverRecorded }} never punched
                            @else
                            {{ $totals->departments }} department(s)
                            @endif
                        </span>
                    </div>
                </a>
            </div>
            <div class="col">
                <a class="att-tile-link" href="{{ $tileUrl('present') }}"
                    title="Show only employees with recorded attendance"
                    aria-current="{{ $status === 'present' ? 'true' : 'false' }}">
                    <div class="att-tile{{ $status === 'present' ? ' is-active' : '' }}">
                        <span class="att-tile-label"><i class="fa fa-check-circle"></i> Present</span>
                        <span class="att-tile-value" style="color:#2e7d32;">{{ $totals->days_present }}</span>
                        <span class="att-tile-sub">days recorded</span>
                    </div>
                </a>
            </div>
            <div class="col">
                <a class="att-tile-link" href="{{ $tileUrl('absent') }}"
                    title="Show only employees with absences"
                    aria-current="{{ $status === 'absent' ? 'true' : 'false' }}">
                    <div class="att-tile{{ $status === 'absent' ? ' is-active' : '' }}">
                        <span class="att-tile-label"><i class="fa fa-user-slash"></i> Absent</span>
                        <span class="att-tile-value" style="color:#c62828;">{{ $totals->days_absent }}</span>
                        <span class="att-tile-sub">working days</span>
                    </div>
                </a>
            </div>
            <div class="col">
                <a class="att-tile-link" href="{{ $tileUrl('late') }}" title="Show only employees with late arrivals"
                    aria-current="{{ $status === 'late' ? 'true' : 'false' }}">
                    <div class="att-tile{{ $status === 'late' ? ' is-active' : '' }}">
                        <span class="att-tile-label"><i class="fa fa-clock"></i> Late</span>
                        <span class="att-tile-value" style="color:#ef6c00;">{{ $totals->late_days }}</span>
                        <span class="att-tile-sub">after {{ substr($lateAfter, 0, 5) }}</span>
                    </div>
                </a>
            </div>
            <div class="col">
                <a class="att-tile-link" href="{{ $tileUrl('on_duty') }}"
                    title="Show only employees who punched on duty"
                    aria-current="{{ $status === 'on_duty' ? 'true' : 'false' }}">
                    <div class="att-tile{{ $status === 'on_duty' ? ' is-active' : '' }}">
                        <span class="att-tile-label"><i class="fa fa-map-marked-alt"></i> On duty</span>
                        <span class="att-tile-value" style="color:#1565c0;">{{ $totals->on_duty_days }}</span>
                        <span class="att-tile-sub">at another site</span>
                    </div>
                </a>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-hourglass-half"></i> Hours</span>
                    <span class="att-tile-value">{{ number_format($totals->total_hours, 1) }}</span>
                    <span class="att-tile-sub">{{ number_format($totals->overtime_hours, 1) }} OT</span>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                @include('attendance.reports.partials.filters', [
                'action' => route('attendance.reports.department'),
                ])
            </div>
        </div>

        {{-- Departments --}}
        <div class="card border-0 shadow-sm" id="attGroups">
            <div class="card-header att-card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>Departments</strong>
                    <span class="badge ms-1">{{ $groups->count() }}</span>
                    @if ($statusLabel)
                    <span class="chip chip-info ms-1"><i class="fa fa-filter"></i> {{ ucfirst($statusLabel) }}</span>
                    @endif
                    <div class="small text-muted">
                        @if ($statusLabel)
                        Showing only employees with {{ $statusLabel }}. The cards above still describe the whole
                        scope. <a href="{{ route('attendance.reports.department', request()->except('status')) }}"
                            class="att-link">Clear filter</a>
                        @else
                        Open a department to see every employee, then open one for their full story.
                        @endif
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <select id="sortSelect" class="form-select form-select-sm" style="width: auto;">
                        <option value="absent" @selected($sort==='absent' )>Most absent first</option>
                        <option value="late" @selected($sort==='late' )>Most late first</option>
                        <option value="hours" @selected($sort==='hours' )>Most hours first</option>
                        <option value="name" @selected($sort==='name' )>Name A–Z</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-light text-nowrap" id="toggleAll"
                        data-expanded="{{ $autoExpand ? '1' : '0' }}">
                        {{ $autoExpand ? 'Collapse all' : 'Expand all' }}
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle mb-0 att-table">
                    <thead>
                        <tr>
                            <th style="width: 42px;"></th>
                            <th>Department</th>
                            <th class="text-center">People</th>
                            <th class="text-center">Present</th>
                            <th class="text-center">Absent</th>
                            <th class="text-center">Late</th>
                            <th class="text-center" title="Punched at a site other than the assigned one">On duty</th>
                            <th class="text-end">Hours</th>
                            <th class="text-end">OT</th>
                            <th class="text-end" style="width: 130px;">Attendance</th>
                        </tr>
                    </thead>
                    <tbody id="grpBody">
                        @forelse ($groups as $group)
                        <tr class="dept-row" data-target="{{ $group->key }}" tabindex="0" role="button"
                            aria-expanded="{{ $autoExpand ? 'true' : 'false' }}">
                            <td class="text-center">
                                <i class="fa fa-chevron-right dept-chevron {{ $autoExpand ? 'open' : '' }}"></i>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $group->department_name }}</div>
                                @if ($group->never_recorded > 0)
                                <div class="small text-danger"><i class="fa fa-exclamation-circle"></i>
                                    {{ $group->never_recorded }} never punched
                                </div>
                                @endif
                            </td>
                            <td class="text-center">{{ $group->headcount }}</td>
                            <td class="text-center">{{ $group->days_present }}</td>
                            <td class="text-center {{ $group->days_absent > 0 ? 'text-danger' : 'text-muted' }}"
                                title="Working days − present − approved leave">
                                {{ $group->days_absent }}
                                @if ($group->leave_days > 0)
                                <div class="small text-muted">{{ $group->leave_days }} leave</div>
                                @endif
                            </td>
                            <td class="text-center {{ $group->late_days > 0 ? 'text-warning' : 'text-muted' }}">
                                {{ $group->late_days }}
                            </td>
                            <td class="text-center">
                                @if ($group->on_duty_days > 0)
                                <span class="chip chip-info">{{ $group->on_duty_days }}</span>
                                @else
                                <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-end">{{ number_format($group->total_hours, 2) }}</td>
                            <td class="text-end text-muted">{{ number_format($group->overtime_hours, 2) }}</td>
                            <td class="text-end">
                                @if ($group->attendance_rate === null)
                                <span class="text-muted small">—</span>
                                @else
                                <div class="rate {{ $rateClass($group->attendance_rate) }}">
                                    <span class="rate-value">{{ number_format($group->attendance_rate, 1) }}%</span>
                                    <span class="rate-bar">
                                        <i data-rate="{{ min(100, $group->attendance_rate) }}"></i>
                                    </span>
                                </div>
                                @endif
                            </td>
                        </tr>

                        <tr class="dept-detail" id="{{ $group->key }}" @if (! $autoExpand) hidden @endif>
                            <td colspan="10" class="p-0">
                                <div class="dept-detail-body">
                                    {{-- Jump into the monthly attendance sheet with this
                                         department already filtered and the report's own
                                         starting month active. --}}
                                    @if ($group->department_id)
                                    <div class="d-flex justify-content-end mb-1">
                                        <a class="att-link small"
                                            href="{{ route('attendance.page', ['department' => $group->department_id, 'month' => $from->format('n'), 'year' => $from->format('Y')]) }}"
                                            title="Open the {{ $from->format('F Y') }} attendance sheet filtered to {{ $group->department_name }}">
                                            <i class="fa fa-table"></i> Monthly attendance sheet
                                        </a>
                                    </div>
                                    @endif
                                    <table class="table table-sm align-middle mb-0 att-inner">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th>Branch</th>
                                                <th class="text-center">Present</th>
                                                <th class="text-center">Absent</th>
                                                <th class="text-center">Late</th>
                                                <th class="text-center">On duty</th>
                                                <th class="text-end">Hours</th>
                                                <th class="text-end">Avg/day</th>
                                                <th class="text-end" style="width: 110px;">Attendance</th>
                                                <th class="text-end">Story</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($group->rows as $row)
                                            <tr class="{{ $row->days_present === 0 ? 'row-none' : '' }}">
                                                <td>
                                                    <a class="att-link"
                                                        href="{{ route('attendance.reports.user', ['user' => $row->user_id] + request()->except('sort', 'status')) }}">
                                                        {{ $row->user_name }}
                                                    </a>
                                                    @if ($row->days_present === 0)
                                                    <span class="chip chip-none"
                                                        title="No attendance record in this period">no records</span>
                                                    @endif
                                                    @if ($row->days_open > 0)
                                                    <span class="chip chip-warn"
                                                        title="Clocked in but never clocked out">{{ $row->days_open }}
                                                        open</span>
                                                    @endif
                                                </td>
                                                <td class="text-muted">{{ $row->branch_name ?: '—' }}</td>
                                                <td class="text-center">{{ $row->days_present }}</td>
                                                <td class="text-center {{ $row->days_absent > 0 ? 'text-danger' : 'text-muted' }}"
                                                    title="Working days − present − approved leave">
                                                    {{ $row->days_absent }}
                                                    @if ($row->leave_days > 0)
                                                    <div class="small text-muted">{{ $row->leave_days }} leave</div>
                                                    @endif
                                                </td>
                                                <td class="text-center {{ $row->late_days > 0 ? 'text-warning' : 'text-muted' }}">
                                                    {{ $row->late_days }}
                                                </td>
                                                <td class="text-center">
                                                    @if ($row->on_duty_days > 0)
                                                    <span class="chip chip-info">{{ $row->on_duty_days }}</span>
                                                    @else
                                                    <span class="text-muted">0</span>
                                                    @endif
                                                </td>
                                                <td class="text-end">{{ number_format($row->total_hours, 2) }}</td>
                                                <td class="text-end text-muted">{{ number_format($row->avg_hours, 2) }}</td>
                                                <td class="text-end">
                                                    @if ($row->attendance_rate === null)
                                                    <span class="text-muted small">—</span>
                                                    @else
                                                    <div class="rate {{ $rateClass($row->attendance_rate) }}">
                                                        <span
                                                            class="rate-value">{{ number_format($row->attendance_rate, 1) }}%</span>
                                                        <span class="rate-bar">
                                                            <i data-rate="{{ min(100, $row->attendance_rate) }}"></i>
                                                        </span>
                                                    </div>
                                                    @endif
                                                </td>
                                                <td class="text-end">
                                                    <a href="{{ route('attendance.reports.user', ['user' => $row->user_id] + request()->except('sort', 'status')) }}"
                                                        class="btn btn-sm btn-outline-secondary text-nowrap"
                                                        title="Open {{ $row->user_name }}'s full attendance story">
                                                        <i class="fa fa-user-clock"></i> Full story
                                                    </a>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted py-5">
                                <i class="fa fa-inbox fa-2x d-block mb-2"></i>
                                @if ($statusLabel)
                                No employee has {{ $statusLabel }} in this period.
                                <a href="{{ route('attendance.reports.department', request()->except('status')) }}"
                                    class="att-link">Clear filter</a>
                                @else
                                No active employee matches these filters.
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>

                    @if ($groups->isNotEmpty())
                    <tfoot class="table-light fw-semibold">
                        <tr>
                            <td></td>
                            <td>{{ $statusLabel ? 'Filtered total' : 'All departments' }}</td>
                            <td class="text-center">{{ $filteredTotals->employees }}</td>
                            <td class="text-center">{{ $filteredTotals->days_present }}</td>
                            <td class="text-center">{{ $filteredTotals->days_absent }}</td>
                            <td class="text-center">{{ $filteredTotals->late_days }}</td>
                            <td class="text-center">{{ $filteredTotals->on_duty_days }}</td>
                            <td class="text-end">{{ number_format($filteredTotals->total_hours, 2) }}</td>
                            <td class="text-end">{{ number_format($filteredTotals->overtime_hours, 2) }}</td>
                            <td class="text-end">
                                @if ($filteredTotals->attendance_rate !== null)
                                {{ number_format($filteredTotals->attendance_rate, 1) }}%
                                @endif
                            </td>
                        </tr>
                    </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        var rows = Array.prototype.slice.call(document.querySelectorAll('.dept-row'));
        var toggleAll = document.getElementById('toggleAll');
        var sortSelect = document.getElementById('sortSelect');

        // Bars are sized from data-rate so no Blade stays inside a style attribute.
        Array.prototype.forEach.call(document.querySelectorAll('.rate-bar > i[data-rate]'), function(bar) {
            var rate = parseFloat(bar.dataset.rate);

            if (!isNaN(rate)) {
                bar.style.width = Math.max(0, Math.min(100, rate)) + '%';
            }
        });

        function setOpen(row, open) {
            var detail = document.getElementById(row.dataset.target);
            var chevron = row.querySelector('.dept-chevron');

            if (detail) detail.hidden = !open;
            if (chevron) chevron.classList.toggle('open', open);
            row.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function isOpen(row) {
            return row.getAttribute('aria-expanded') === 'true';
        }

        rows.forEach(function(row) {
            row.addEventListener('click', function() {
                setOpen(row, !isOpen(row));
            });

            row.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    setOpen(row, !isOpen(row));
                }
            });
        });

        if (toggleAll) {
            toggleAll.addEventListener('click', function() {
                var open = toggleAll.dataset.expanded !== '1';

                rows.forEach(function(row) {
                    setOpen(row, open);
                });

                toggleAll.dataset.expanded = open ? '1' : '0';
                toggleAll.textContent = open ? 'Collapse all' : 'Expand all';
            });
        }

        if (sortSelect) {
            sortSelect.addEventListener('change', function() {
                var url = new URL(window.location.href);
                url.searchParams.set('sort', sortSelect.value);
                window.location.href = url.toString();
            });
        }
    })();
</script>

@include('attendance.reports.partials.report-style')
@endsection
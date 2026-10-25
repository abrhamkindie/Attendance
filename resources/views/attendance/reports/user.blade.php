@extends('layouts.master')

@section('content')
@php
$initials = collect(preg_split('/\s+/', trim((string) $user->name)))
->filter()
->take(2)
->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
->implode('');

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

$hasAttendance = $attendance->isNotEmpty();
$hasPostings = $postings->isNotEmpty();

$notStarted = $joinedOn && $joinedOn->gt($to);

$filterKeys = ['from', 'to', 'start_time', 'grace'];

// Branch / department reports stay with the report roles; an employee opening
// their own story goes back to their own attendance page instead.
$canViewReports = auth()->user()->hasAnyRole(['Admin', 'Super Admin', 'HR']);
$backUrl = $canViewReports
? route('attendance.reports.department', request()->only(['from', 'to', 'branch_id', 'department_id', 'q', 'sort']))
: route('attendance.index');
$shiftLabel = substr($lateAfter, 0, 5);
@endphp

<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">{{ $user->name }} <span class="fw-normal text-muted">— attendance story</span></h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        @if ($canViewReports)
                        <li class="breadcrumb-item">
                            <a href="{{ route('attendance.reports.branch', request()->only(['from', 'to'])) }}">Attendance Reports</a>
                        </li>
                        <li class="breadcrumb-item"><a href="{{ $backUrl }}">By department</a></li>
                        @endif
                        <li class="breadcrumb-item active">Full story</li>
                    </ul>
                </div>
                <div class="col-auto d-flex flex-wrap gap-2">
                    <a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-arrow-left"></i> {{ $canViewReports ? 'Back to report' : 'My attendance' }}
                    </a>
                    <button type="button" class="btn btn-sm btn-light" onclick="window.print()">
                        <i class="fa fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>

        {{-- Who this report is about --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                    <div class="att-avatar">{{ $initials ?: '?' }}</div>
                    <div class="me-auto">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <h5 class="mb-0">{{ $user->name }}</h5>
                            <span class="chip {{ strcasecmp((string) $user->status, 'Active') === 0 ? 'chip-good' : 'chip-none' }}">
                                {{ $user->status ?: 'Unknown' }}
                            </span>
                        </div>
                        <div class="small text-muted">{{ $user->email ?: 'no email on file' }}</div>
                    </div>
                </div>

                <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 g-3 att-meta-grid">
                    <div class="col">
                        <span class="att-meta-label">Branch</span>
                        <span class="att-meta-value">{{ $user->branch->name ?? '—' }}</span>
                    </div>
                    <div class="col">
                        <span class="att-meta-label">Department</span>
                        <span class="att-meta-value">{{ $user->department?->department ?? '—' }}</span>
                    </div>
                    <div class="col">
                        <span class="att-meta-label">Joined</span>
                        <span class="att-meta-value">{{ $joinedOn ? $joinedOn->format('d M Y') : '—' }}</span>
                    </div>
                    <div class="col">
                        <span class="att-meta-label">Assigned site</span>
                        <span class="att-meta-value">
                            @if ($assignedLocation)
                            {{ $assignedLocation->name }}
                            <span class="badge bg-light text-dark">{{ $source }}</span>
                            @else
                            <span class="text-danger">Not configured</span>
                            @endif
                        </span>
                    </div>
                    <div class="col">
                        <span class="att-meta-label">Branch site</span>
                        <span class="att-meta-value">{{ $user->branchLocation()?->name ?? '—' }}</span>
                    </div>
                    <div class="col">
                        <span class="att-meta-label">Own location</span>
                        <span class="att-meta-value">{{ $user->location->name ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary tiles: one row --}}
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 g-3 mb-3">
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-check-circle"></i> Present</span>
                    <span class="att-tile-value" style="color:#2e7d32;">{{ $summary->days_present }}</span>
                    <span class="att-tile-sub"
                        title="Required = working days since joining (public holidays excluded) − approved leave">
                        of {{ $summary->days_required }} required day(s)
                    </span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile{{ $summary->days_absent > 0 ? ' att-tile-alert' : '' }}">
                    <span class="att-tile-label"><i class="fa fa-user-slash"></i> Absent</span>
                    <span class="att-tile-value">{{ $summary->days_absent }}</span>
                    <span class="att-tile-sub">
                        @if ($summary->leave_days > 0)
                        {{ $summary->leave_days }} of {{ $workingDays }} days on approved leave
                        @else
                        days without a punch
                        @endif
                    </span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-clock"></i> Late</span>
                    <span class="att-tile-value" style="color:#ef6c00;">{{ $summary->late_days }}</span>
                    <span class="att-tile-sub">after {{ $shiftLabel }}</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-map-marked-alt"></i> On duty</span>
                    <span class="att-tile-value" style="color:#1565c0;">{{ $summary->on_duty_days }}</span>
                    <span class="att-tile-sub">at another site</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-hourglass-half"></i> Hours</span>
                    <span class="att-tile-value">{{ number_format($summary->total_hours, 1) }}</span>
                    <span class="att-tile-sub">{{ number_format($summary->overtime_hours, 1) }} OT</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile {{ $rateClass($summary->attendance_rate) }}">
                    <span class="att-tile-label"><i class="fa fa-chart-line"></i> Attendance</span>
                    @if ($summary->attendance_rate === null)
                    <span class="att-tile-value">—</span>
                    <span class="att-tile-sub">not measurable</span>
                    @else
                    <span class="att-tile-value">{{ number_format($summary->attendance_rate, 1) }}%</span>
                    <span class="rate-bar mt-1"><i data-rate="{{ min(100, $summary->attendance_rate) }}"></i></span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('attendance.reports.user', $user) }}" id="attUserFilterForm"
                    class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1" for="attFrom">From</label>
                        <input type="date" name="from" id="attFrom" value="{{ $from->toDateString() }}"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1" for="attTo">To</label>
                        <input type="date" name="to" id="attTo" value="{{ $to->toDateString() }}"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1" for="attStart">Shift start</label>
                        <input type="time" name="start_time" id="attStart" value="{{ $startTime }}"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1" for="attGrace">Grace (min)</label>
                        <input type="number" name="grace" id="attGrace" min="0" max="120" value="{{ $grace }}"
                            class="form-control form-control-sm">
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-brand">
                            <i class="fa fa-filter"></i> Apply
                        </button>
                        <a href="{{ route('attendance.reports.user', $user) }}" class="btn btn-sm btn-light">Reset</a>
                    </div>
                </form>

                <div class="d-flex flex-wrap align-items-center gap-2 mt-3 pt-2 border-top">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Quick date ranges">
                        <button type="button" class="btn btn-outline-secondary" data-range="month">This month</button>
                        <button type="button" class="btn btn-outline-secondary" data-range="last-month">Last month</button>
                        <button type="button" class="btn btn-outline-secondary" data-range="last-30">Last 30 days</button>
                    </div>
                    <span class="small text-muted ms-auto text-end">
                        <i class="fa fa-clock"></i> Late = clock-in after <strong>{{ $shiftLabel }}</strong>
                        ({{ $startTime }} + {{ $grace }} min)
                        &middot; <i class="fa fa-calendar-alt"></i> {{ $workingDays }} working day(s), Mon–Sat
                    </span>
                </div>
            </div>
        </div>

        {{-- Temporary postings --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
                <strong>Temporary postings</strong>
                <span class="badge bg-light text-dark">{{ $postings->count() }}</span>
                <span class="small text-muted ms-md-auto">Dated location changes recorded for this employee.</span>
            </div>

            @if (! $hasPostings)
            <div class="card-body">
                <div class="att-empty att-empty-sm">
                    <i class="fa fa-map-marker"></i>
                    <h6>No temporary postings</h6>
                    <p>This employee has always been checked against their normal site.</p>
                </div>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 att-table">
                    <thead class="table-light">
                        <tr>
                            <th>Site</th>
                            <th>From</th>
                            <th>Until</th>
                            <th>State</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($postings as $posting)
                        <tr>
                            <td class="fw-semibold">{{ $posting->location->name ?? '—' }}</td>
                            <td>{{ $posting->starts_on?->format('d M Y') ?? '—' }}</td>
                            <td>{{ $posting->ends_on?->format('d M Y') ?? 'open' }}</td>
                            <td>
                                <span
                                    class="chip {{ $posting->state === 'active' ? 'chip-good' : ($posting->state === 'upcoming' ? 'chip-info' : 'chip-muted') }}">
                                    {{ ucfirst((string) $posting->state) }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $posting->note ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>

        {{-- Every day --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex flex-wrap align-items-center gap-2">
                <strong>Every day</strong>
                <span class="badge bg-light text-dark">{{ $attendance->count() }}</span>
                @if ($summary->days_open > 0)
                <span class="chip chip-warn">{{ $summary->days_open }} open shift(s)</span>
                @endif
                <span class="small text-muted ms-md-auto d-none d-md-inline">
                    “On duty” means the punch was accepted at a site other than the assigned one.
                </span>
            </div>

            @if (! $hasAttendance)
            <div class="card-body">
                <div class="att-empty">
                    <i class="fa fa-calendar-times"></i>
                    <h6>No attendance recorded for this period</h6>
                    @if ($notStarted)
                    <p>
                        {{ $user->name }} joined on <strong>{{ $joinedOn->format('d M Y') }}</strong>, which is after
                        {{ $to->format('d M Y') }}. Pick a later range to see anything.
                    </p>
                    @else
                    <p>
                        Nothing was punched between <strong>{{ $from->format('d M Y') }}</strong> and
                        <strong>{{ $to->format('d M Y') }}</strong>. That is
                        {{ $workingDays }} working day(s), all counted as absent.
                    </p>
                    @endif
                    <a href="{{ route('attendance.reports.user', $user) }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-undo"></i> Back to this month
                    </a>
                </div>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 att-table">
                    <thead class="table-light">
                        <tr>
                            <th>Date</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Status</th>
                            <th>Site</th>
                            <th class="text-end">Distance</th>
                            <th>Reason for HR</th>
                            <th class="text-end">Break</th>
                            <th class="text-end">Hours</th>
                            <th class="text-end">OT</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($attendance as $record)
                        @php
                        $inSite = $record->clock_in_location_id
                        ? ($locations[$record->clock_in_location_id] ?? null)
                        : ($record->location_id ? ($locations[$record->location_id] ?? null) : null);
                        $outSite = $record->clock_out_location_id
                        ? ($locations[$record->clock_out_location_id] ?? null)
                        : null;
                        $isLate = (bool) ($record->is_late ?? false);
                        $isOpen = $record->clock_in && ! $record->clock_out;
                        $isDuty = $record->clock_in_match === 'on_duty' || $record->clock_out_match === 'on_duty';
                        $dutyBranch = $record->duty_branch_id
                        ? ($dutyBranches[$record->duty_branch_id] ?? null)
                        : null;
                        @endphp
                        <tr>
                            <td class="text-nowrap">
                                <span class="fw-semibold">{{ \Carbon\Carbon::parse($record->date)->format('D d M') }}</span>
                            </td>
                            <td class="text-nowrap">
                                {{ $record->clock_in ? \Carbon\Carbon::parse($record->clock_in)->format('H:i') : '—' }}
                            </td>
                            <td class="text-nowrap">
                                @if ($record->clock_out)
                                {{ \Carbon\Carbon::parse($record->clock_out)->format('H:i') }}
                                @else
                                <span class="chip chip-warn">open</span>
                                @endif
                            </td>
                            <td>
                                @if ($isDuty)
                                <span class="chip chip-info">on duty</span>
                                @elseif ($isLate)
                                <span class="chip chip-warn">late</span>
                                @elseif ($isOpen)
                                <span class="chip chip-warn">not closed</span>
                                @else
                                <span class="chip chip-good">present</span>
                                @endif
                            </td>
                            <td>
                                <div>{{ $inSite->name ?? '—' }}</div>
                                @if ($outSite && $outSite->id !== ($inSite->id ?? null))
                                <div class="small text-muted">out: {{ $outSite->name }}</div>
                                @endif
                            </td>
                            <td class="text-end text-muted small text-nowrap">
                                {{ $record->clock_in_distance_m !== null ? round($record->clock_in_distance_m) . ' m' : '—' }}
                                @if ($record->clock_out_distance_m !== null)
                                <span class="text-muted">&rarr; {{ round($record->clock_out_distance_m) }} m</span>
                                @endif
                            </td>
                            <td class="small">
                                {{ $record->duty_note ?: '—' }}
                                @if ($dutyBranch)
                                <span class="text-muted d-block">at {{ $dutyBranch }}</span>
                                @endif
                            </td>
                            <td class="text-end text-muted">{{ number_format((float) $record->break_hours, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $record->total_hours, 2) }}</td>
                            <td class="text-end text-muted">{{ number_format((float) $record->overtime_hours, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

<script>
    (function() {
        // Bars are sized from data-rate so no Blade stays inside a style attribute.
        Array.prototype.forEach.call(document.querySelectorAll('.rate-bar > i[data-rate]'), function(bar) {
            var rate = parseFloat(bar.dataset.rate);

            if (!isNaN(rate)) {
                bar.style.width = Math.max(0, Math.min(100, rate)) + '%';
            }
        });

        var form = document.getElementById('attUserFilterForm');
        var fromInput = document.getElementById('attFrom');
        var toInput = document.getElementById('attTo');

        function iso(date) {
            var month = String(date.getMonth() + 1).padStart(2, '0');
            var day = String(date.getDate()).padStart(2, '0');

            return date.getFullYear() + '-' + month + '-' + day;
        }

        Array.prototype.forEach.call(document.querySelectorAll('[data-range]'), function(button) {
            button.addEventListener('click', function() {
                if (!form || !fromInput || !toInput) return;

                var today = new Date();
                var start, end;

                if (button.dataset.range === 'last-month') {
                    start = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    end = new Date(today.getFullYear(), today.getMonth(), 0);
                } else if (button.dataset.range === 'last-30') {
                    end = today;
                    start = new Date(today.getTime() - 29 * 86400000);
                } else {
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = today;
                }

                fromInput.value = iso(start);
                toInput.value = iso(end);
                form.submit();
            });
        });
    })();
</script>

<style>
    .btn-brand {
        background-color: #395750;
        border-color: #395750;
        color: #fff;
        font-weight: 600;
    }

    .btn-brand:hover,
    .btn-brand:focus {
        background-color: #2c443f;
        border-color: #2c443f;
        color: #fff;
    }

    .btn-outline-secondary {
        color: #395750;
        border-color: #395750;
    }

    /* ---- Identity ---- */
    .att-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 52px;
        height: 52px;
        border-radius: 50%;
        background: #eef3f1;
        color: #395750;
        font-weight: 700;
        font-size: 18px;
        letter-spacing: .02em;
    }

    .att-meta-grid {
        border-top: 1px solid #eef1f0;
        padding-top: 14px;
    }

    .att-meta-label {
        display: block;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #98a2a0;
    }

    .att-meta-value {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #2c443f;
        word-break: break-word;
    }

    /* ---- KPI tiles ---- */
    .att-tile {
        display: flex;
        flex-direction: column;
        gap: 2px;
        height: 100%;
        padding: 14px 16px;
        background: #fff;
        border: 1px solid #eef1f0;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
    }

    .att-tile-label {
        font-size: 12px;
        color: #6c757d;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .att-tile-label i {
        color: #7A9F95;
        margin-right: 4px;
    }

    .att-tile-value {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.15;
        color: #395750;
    }

    .att-tile-sub {
        font-size: 12px;
        color: #98a2a0;
    }

    .att-tile-alert {
        border-color: #f3d6d4;
        background: #fffbfb;
    }

    .att-tile-alert .att-tile-value {
        color: #c62828;
    }

    .att-tile.rate-good .att-tile-value {
        color: #2e7d32;
    }

    .att-tile.rate-warn .att-tile-value {
        color: #ef6c00;
    }

    .att-tile.rate-bad .att-tile-value {
        color: #c62828;
    }

    .att-tile.rate-good .rate-bar>i {
        background: #2e7d32;
    }

    .att-tile.rate-warn .rate-bar>i {
        background: #ef6c00;
    }

    .att-tile.rate-bad .rate-bar>i {
        background: #c62828;
    }

    /* ---- Attendance bar ---- */
    .rate-bar {
        display: block;
        width: 100%;
        height: 4px;
        border-radius: 999px;
        background: #eef1f0;
        overflow: hidden;
    }

    .rate-bar>i {
        display: block;
        width: 0;
        height: 100%;
        border-radius: 999px;
        background: #395750;
        transition: width .25s ease;
    }

    /* ---- Chips ---- */
    .chip {
        display: inline-block;
        padding: 1px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.7;
        white-space: nowrap;
    }

    .chip-good {
        background: #e7f4e8;
        color: #2e7d32;
    }

    .chip-warn {
        background: #fff4e5;
        color: #b26a00;
    }

    .chip-none {
        background: #fdecea;
        color: #c62828;
    }

    .chip-info {
        background: #e7f0fb;
        color: #1565c0;
    }

    .chip-muted {
        background: #f1f3f2;
        color: #6c757d;
    }

    /* ---- Tables ---- */
    .att-table>thead>tr>th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .03em;
        color: #6c757d;
        font-weight: 600;
        white-space: nowrap;
    }

    /* ---- Empty states: shown instead of a header-only table ---- */
    .att-empty {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
    }

    .att-empty-sm {
        padding: 18px 12px;
    }

    .att-empty>i {
        display: block;
        margin-bottom: 10px;
        font-size: 34px;
        color: #b7c9c4;
    }

    .att-empty-sm>i {
        font-size: 24px;
        margin-bottom: 6px;
    }

    .att-empty h6 {
        margin-bottom: 6px;
        color: #395750;
        font-weight: 700;
    }

    .att-empty p {
        max-width: 520px;
        margin: 0 auto 14px;
        font-size: 13px;
    }

    @media (max-width: 767.98px) {
        .att-tile {
            padding: 10px 12px;
        }

        .att-tile-value {
            font-size: 18px;
        }
    }

    @media print {

        .page-header .col-auto,
        #attUserFilterForm,
        .btn-group[role="group"] {
            display: none !important;
        }

        .att-tile {
            box-shadow: none;
        }

        .card {
            break-inside: avoid;
        }
    }
</style>
@endsection
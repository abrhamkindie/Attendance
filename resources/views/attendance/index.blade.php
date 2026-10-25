@extends('layouts.master')

@section('content')

<!-- Custom Brand CSS -->
<style>
    /* Brand Primary Color (#395750) */
    .btn-brand {
        background-color: #395750;
        border-color: #395750;
        color: white;
        font-weight: bold;
    }

    .btn-brand:hover {
        background-color: #2c443f;
        border-color: #2c443f;
        color: white;
    }

    .btn-brand-outline {
        background-color: transparent;
        border-color: #395750;
        color: #395750;
        font-weight: bold;
    }

    .btn-brand-outline:hover {
        background-color: #395750;
        color: white;
    }

    /* Customizing the QR Modal 'Open Direct Link' button to be Brand Green instead of Red */
    .btn-brand-modal {
        background-color: #395750;
        color: white;
        border: none;
        font-weight: bold;
        transition: all 0.3s;
    }

    .btn-brand-modal:hover {
        background-color: #2c443f;
        color: white;
    }

    /* Clock circle styling */
    .punch-circle {
        margin: 0 auto;
        display: inline-block;
        border-radius: 50%;
        width: 120px;
        height: 120px;
        line-height: 120px;
        border: 6px solid #395750;
        text-align: center;
        font-size: 18px;
        font-weight: bold;
        color: #395750;
        box-shadow: 0 4px 10px rgba(57, 87, 80, 0.2);
    }

    /* ---- Rest of the page (the timesheet card above keeps its own styles) ---- */
    .att-home {
        --brand: #395750;
        --brand-dark: #2c443f;
        --brand-accent: #7A9F95;
        --brand-line: #e3ebe8;
        --ink: #26332f;
        --muted: #7d8b87;
    }

    .att-home .att-card {
        background: #fff;
        border: 1px solid var(--brand-line);
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(23, 46, 41, .05);
        overflow: hidden;
    }

    .att-home .att-card-head {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: 13px 18px;
        border-bottom: 1px solid #f0f3f2;
    }

    .att-home .att-card-title {
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        color: var(--ink);
    }

    .att-home .att-card-title i {
        margin-right: 6px;
        color: var(--brand-accent);
    }

    .att-home .att-card-sub {
        margin-left: auto;
        font-size: 12px;
        color: var(--muted);
    }

    .att-home .att-card-body {
        padding: 16px 18px;
    }

    /* Columns stack on small screens, so keep the theme card's bottom gap. */
    .att-home .col-md-4>.att-card {
        margin-bottom: 30px;
    }

    /* Statistics: one row per metric, brand-coloured bars. */
    .att-home .att-stat+.att-stat {
        margin-top: 14px;
    }

    .att-home .att-stat-top {
        display: flex;
        align-items: baseline;
        justify-content: space-between;
        gap: .5rem;
        margin-bottom: 6px;
    }

    .att-home .att-stat-label {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--muted);
    }

    .att-home .att-stat-value {
        font-size: 13px;
        font-weight: 700;
        color: var(--ink);
        white-space: nowrap;
    }

    .att-home .att-stat-value small {
        font-size: 11px;
        font-weight: 500;
        color: var(--muted);
    }

    .att-home .att-bar {
        height: 6px;
        border-radius: 999px;
        background: #eef2f1;
        overflow: hidden;
    }

    .att-home .att-bar>span {
        display: block;
        height: 100%;
        border-radius: 999px;
        background: var(--brand);
    }

    .att-home .att-bar--week>span {
        background: var(--brand-accent);
    }

    .att-home .att-bar--month>span {
        background: #4a7a70;
    }

    .att-home .att-bar--remaining>span {
        background: #c8a45c;
    }

    .att-home .att-bar--overtime>span {
        background: #3f6f8f;
    }

    /* Today activity: a small vertical timeline. */
    .att-home .att-timeline {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .att-home .att-timeline li {
        position: relative;
        padding-left: 22px;
    }

    .att-home .att-timeline li+li {
        margin-top: 14px;
    }

    .att-home .att-timeline li::before {
        content: "";
        position: absolute;
        left: 0;
        top: 5px;
        width: 9px;
        height: 9px;
        border-radius: 50%;
        background: var(--brand-accent);
        box-shadow: 0 0 0 3px #eef4f2;
    }

    .att-home .att-timeline li::after {
        content: "";
        position: absolute;
        left: 4px;
        top: 16px;
        bottom: -13px;
        width: 1px;
        background: var(--brand-line);
    }

    .att-home .att-timeline li:last-child::after {
        display: none;
    }

    .att-home .att-event {
        margin: 0;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink);
    }

    .att-home .att-time {
        margin: 2px 0 0;
        font-size: 12px;
        color: var(--muted);
    }

    .att-home .att-time i {
        margin-right: 4px;
        color: #b3c2be;
    }

    /* Filters: pale green panel, same language as the report pages. */
    .att-home .att-filter {
        padding: 12px 14px;
        background: #f2f7f5;
        border: 1px solid #dbe7e3;
        border-left: 3px solid var(--brand);
        border-radius: 10px;
    }

    .att-home .att-filter-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: .5rem;
        margin-bottom: .75rem;
    }

    .att-home .att-filter-title {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: var(--brand);
    }

    .att-home .att-filter-title i {
        margin-right: 4px;
        color: var(--brand-accent);
    }

    .att-home .att-filter-hint {
        font-size: 11px;
        color: #7d8f8a;
    }

    .att-home .att-filter .form-label {
        margin-bottom: .2rem;
        font-size: 11px;
        font-weight: 600;
        color: #7d8f8a;
    }

    /* History table, empty state and pager. */
    .att-home .att-table {
        margin: 0;
    }

    .att-home .att-table thead th {
        padding: 9px 18px;
        background: #fbfcfc;
        border-bottom: 1px solid var(--brand-line);
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: #7d8b87;
        white-space: nowrap;
    }

    .att-home .att-table tbody td {
        padding: 10px 18px;
        font-size: 13.5px;
        font-variant-numeric: tabular-nums;
        vertical-align: middle;
        border-color: #f1f4f3;
    }

    .att-home .att-table tbody tr:hover {
        background: #f8fbfa;
    }

    .att-home .att-day {
        display: block;
        font-size: 11px;
        color: var(--muted);
    }

    .att-home .att-empty {
        padding: 44px 16px;
        text-align: center;
    }

    .att-home .att-empty i {
        display: block;
        margin-bottom: 10px;
        font-size: 26px;
        color: #c3d3cf;
    }

    .att-home .att-empty strong {
        display: block;
        margin-bottom: 4px;
        font-size: 14px;
        color: var(--ink);
    }

    .att-home .att-empty p {
        margin: 0 0 12px;
        font-size: 13px;
        color: var(--muted);
    }

    .att-home .att-total {
        font-size: 12px;
        color: var(--muted);
    }

    .att-home .att-pager .pagination {
        margin: 0;
    }

    .att-home .att-pager .page-link {
        border-color: var(--brand-line);
        color: var(--brand);
        font-size: 12.5px;
        padding: .25rem .6rem;
    }

    .att-home .att-pager .active .page-link {
        background: var(--brand);
        border-color: var(--brand);
        color: #fff;
    }

    .att-home .att-pager .disabled .page-link {
        color: #c2cdc9;
    }

    @media (max-width: 575.98px) {
        .att-home .att-filter-head .ms-auto {
            width: 100%;
            justify-content: flex-end;
        }

        .att-home .att-table thead th,
        .att-home .att-table tbody td {
            padding-left: 8px;
            padding-right: 8px;
        }
    }
</style>

@php
// One period drives three things: the history heading, the month the monthly
// sheet opens on, and the range "My full report" opens with.
$hasDate = request()->filled('date');
$rangeStart = $hasDate
? \Carbon\Carbon::parse(request('date'))
: ($month
? \Carbon\Carbon::create($year ?? now()->year, $month, 1)
: ($year ? \Carbon\Carbon::create($year, 1, 1) : now()->startOfMonth()));

$rangeEnd = $hasDate
? $rangeStart->copy()
: ($month
? $rangeStart->copy()->endOfMonth()
: ($year ? $rangeStart->copy()->endOfYear() : now()->endOfMonth()));

// Mirrors the controller: a date wins, then month/year, then the
// current-month default opened on a first visit.
$hasFilter = $month !== null || $year !== null || $hasDate;

$period = $hasDate
? $rangeStart->format('d M Y')
: ($month && $year ? $rangeStart->format('F Y') : ($month ? $rangeStart->format('F') : ($year ? (string) $year : null)));

$sheetUrl = route('attendance.page', [
'month' => $month ?? ($hasDate ? $rangeStart->month : now()->month),
'year' => $year ?? ($hasDate ? $rangeStart->year : now()->year),
]);

$reportUrl = route('attendance.reports.user', [
'user' => auth()->id(),
'from' => $rangeStart->toDateString(),
'to' => $rangeEnd->toDateString(),
]);
@endphp

<!-- Page Wrapper -->
<div class="page-wrapper att-home">
    <!-- Page Content -->
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Attendance</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Attendance</li>
                    </ul>
                </div>
                <div class="col-auto ms-auto d-flex flex-wrap gap-2">
                    <a href="{{ $sheetUrl }}" class="btn btn-sm btn-light border"
                        title="Open the monthly sheet for {{ $rangeStart->format('F Y') }}">
                        <i class="fa fa-table"></i> Monthly sheet
                    </a>
                    <a href="{{ $reportUrl }}" class="btn btn-sm btn-brand"
                        title="Open your full attendance report for {{ $period ?? $rangeStart->format('F Y') }}">
                        <i class="fa fa-file-alt"></i> My full report
                    </a>
                </div>
            </div>
        </div>
        <!-- /Page Header -->

        <div class="row">
            <div class="col-md-4">
                <div class="card punch-status">
                    <div class="card-body">
                        <h5 class="card-title">Timesheet <small
                                class="text-muted">{{ \Carbon\Carbon::today()->format('d M Y') }}</small></h5>

                        {{-- Which geofence this employee must punch from (resolved on the server). --}}
                        @if ($attendanceLocation)
                        <div class="alert alert-light border py-2 px-3 mb-3 small">
                            <i class="fa fa-map-marker-alt" style="color:#395750;"></i>
                            Assigned site: <strong>{{ $attendanceLocation->name }}</strong>
                            &middot; allowed radius {{ $attendanceLocation->radius_m }} m
                        </div>
                        @else
                        <div class="alert alert-warning py-2 px-3 mb-3 small">
                            <i class="fa fa-exclamation-triangle"></i>
                            You have no assigned site yet — punch at any office and add a reason for HR.
                        </div>
                        @endif

                        <div class="punch-info text-center">
                            <!-- Applied brand color to circle -->
                            <div class="punch-circle">
                                <span id="liveWorkedHours">
                                    0.00 hrs
                                </span>
                            </div>
                        </div>

                        @if ($todayAttendance && $todayAttendance->clock_in)
                        <div class="d-flex justify-content-between punch-det mt-3">
                            <div>
                                <h6>Clock In at</h6>
                                <p>{{ \Carbon\Carbon::parse($todayAttendance->clock_in)->format('h:i A') }}</p>
                            </div>
                            @if ($todayAttendance->clock_out)
                            <div class="text-right">
                                <h6>Clock Out at</h6>
                                <p>{{ \Carbon\Carbon::parse($todayAttendance->clock_out)->format('h:i A') }}</p>
                            </div>
                            @endif
                        </div>

                        <div class="punch-btn-section text-center mt-4">
                            @if (!$todayAttendance->clock_out)
                            <!-- User is Clocked In: Show Clock Out Options -->
                            <button type="button" class="btn btn-brand w-100 mb-2"
                                onclick="handleClock('out', this)" @disabled(! $canPunch)>
                                <i class="fa fa-sign-out-alt"></i> Direct Clock Out
                            </button>

                            <button type="button" class="btn btn-brand-outline w-100" data-bs-toggle="modal" data-bs-target="#qrOutModal">
                                <i class="fas fa-qrcode"></i> Scan QR to Clock Out
                            </button>
                            @else
                            <button class="btn btn-secondary w-100" disabled><i class="fa fa-check"></i> Clocked Out</button>
                            @endif
                        </div>
                        @else
                        <!-- User is NOT Clocked In: Show Clock In Options -->
                        <div class="punch-det mt-3 text-center">
                            <h6>No Clock In Today</h6>
                        </div>
                        <div class="punch-btn-section text-center mt-4">
                            <button type="button" class="btn btn-brand w-100 mb-2"
                                onclick="handleClock('in', this)" @disabled(! $canPunch)>
                                <i class="fa fa-sign-in-alt"></i> Direct Clock In
                            </button>

                            <button type="button" class="btn btn-brand-outline w-100" data-bs-toggle="modal" data-bs-target="#qrInModal">
                                <i class="fas fa-qrcode"></i> Scan QR to Clock In
                            </button>
                        </div>
                        @endif

                        {{-- Punching away from the assigned site: HR needs a reason. --}}
                        @php $dutyOpen = session('errors') && session('errors')->has('duty_note'); @endphp
                        <div class="mt-3">
                            <a class="small text-decoration-none" style="color:#395750;" data-bs-toggle="collapse"
                                href="#dutyPanel" role="button" aria-expanded="{{ $dutyOpen ? 'true' : 'false' }}">
                                <i class="fa fa-map-marked-alt"></i> Punching on duty at another site?
                            </a>
                            <div class="collapse {{ $dutyOpen ? 'show' : '' }}" id="dutyPanel">
                                <div class="border rounded p-2 mt-2 text-start" style="background:#f7f9f8;">
                                    <label class="small mb-1 d-block">Reason
                                        <span class="text-muted">(required when you are not at your assigned site)</span>
                                    </label>
                                    <select class="form-select form-select-sm mb-2" id="dutyReason">
                                        <option value="">-- select a reason --</option>
                                        @foreach ($dutyReasons as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" class="form-control form-control-sm" id="dutyText"
                                        maxlength="180" placeholder="Short note for HR (optional)">
                                    <div class="small text-muted mt-1">
                                        Pick a reason or type a note — either one is enough.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="statistics mt-4">
                            <div class="row">
                                <div class="col-md-6 col-6 text-center">
                                    <div class="stats-box">
                                        <p class="mb-1 text-muted">Break</p>
                                        <h6 class="mb-0">{{ $todayAttendance->break_hours ?? '0.00' }} hrs</h6>
                                    </div>
                                </div>
                                <div class="col-md-6 col-6 text-center">
                                    <div class="stats-box">
                                        <p class="mb-1 text-muted">Overtime</p>
                                        <h6 class="mb-0">{{ $todayAttendance->overtime_hours ?? '0.00' }} hrs</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden Forms for Submission -->
            {{-- The browser only reports the GPS reading. Accept/reject is decided on the server. --}}
            <form id="clockInForm" action="{{ route('attendance.punchIn') }}" method="POST" style="display: none;">
                @csrf
                <input type="hidden" name="latitude" id="clockInLatitude">
                <input type="hidden" name="longitude" id="clockInLongitude">
                <input type="hidden" name="accuracy" id="clockInAccuracy">
                <input type="hidden" name="duty_note" id="clockInDutyNote">
            </form>

            <form id="clockOutForm" action="{{ route('attendance.punchOut') }}" method="POST" style="display: none;">
                @csrf
                <input type="hidden" name="latitude" id="clockOutLatitude">
                <input type="hidden" name="longitude" id="clockOutLongitude">
                <input type="hidden" name="accuracy" id="clockOutAccuracy">
                <input type="hidden" name="duty_note" id="clockOutDutyNote">
            </form>

            <div class="col-md-4">
                <div class="att-card">
                    <div class="att-card-head">
                        <h5 class="att-card-title"><i class="fa fa-chart-line"></i> Statistics</h5>
                        <span class="att-card-sub">{{ now()->format('M Y') }}</span>
                    </div>
                    <div class="att-card-body">
                        @php
                        $todayPercent = $todayAttendance
                        ? min(($todayAttendance->total_hours / 8) * 100, 100)
                        : 0;
                        $weekPercent = min(($weekTotal / 40) * 100, 100);
                        $monthPercent = min(($monthTotal / 160) * 100, 100);
                        $remainingPercent = max(((160 - $monthTotal) / 160) * 100, 0);
                        $overtimePercent = min(($monthOvertime / 20) * 100, 100);
                        @endphp

                        <div class="att-stat">
                            <div class="att-stat-top">
                                <span class="att-stat-label">Today</span>
                                <span class="att-stat-value">
                                    {{ $todayAttendance->total_hours ?? '0.00' }} <small>/ 8 hrs</small>
                                </span>
                            </div>
                            <div class="att-bar" role="progressbar" aria-label="Today"
                                aria-valuenow="{{ round($todayPercent) }}" aria-valuemin="0" aria-valuemax="100">
                                <span data-width="{{ $todayPercent }}"></span>
                            </div>
                        </div>

                        <div class="att-stat">
                            <div class="att-stat-top">
                                <span class="att-stat-label">This week</span>
                                <span class="att-stat-value">{{ $weekTotal }} <small>/ 40 hrs</small></span>
                            </div>
                            <div class="att-bar att-bar--week" role="progressbar" aria-label="This week"
                                aria-valuenow="{{ round($weekPercent) }}" aria-valuemin="0" aria-valuemax="100">
                                <span data-width="{{ $weekPercent }}"></span>
                            </div>
                        </div>

                        <div class="att-stat">
                            <div class="att-stat-top">
                                <span class="att-stat-label">This month</span>
                                <span class="att-stat-value">{{ $monthTotal }} <small>/ 160 hrs</small></span>
                            </div>
                            <div class="att-bar att-bar--month" role="progressbar" aria-label="This month"
                                aria-valuenow="{{ round($monthPercent) }}" aria-valuemin="0" aria-valuemax="100">
                                <span data-width="{{ $monthPercent }}"></span>
                            </div>
                        </div>

                        <div class="att-stat">
                            <div class="att-stat-top">
                                <span class="att-stat-label">Remaining</span>
                                <span class="att-stat-value">{{ max(160 - $monthTotal, 0) }} <small>/ 160 hrs</small></span>
                            </div>
                            <div class="att-bar att-bar--remaining" role="progressbar" aria-label="Remaining"
                                aria-valuenow="{{ round($remainingPercent) }}" aria-valuemin="0" aria-valuemax="100">
                                <span data-width="{{ $remainingPercent }}"></span>
                            </div>
                        </div>

                        <div class="att-stat">
                            <div class="att-stat-top">
                                <span class="att-stat-label">Overtime</span>
                                <span class="att-stat-value">{{ $monthOvertime }} <small>hrs this month</small></span>
                            </div>
                            <div class="att-bar att-bar--overtime" role="progressbar" aria-label="Overtime"
                                aria-valuenow="{{ round($overtimePercent) }}" aria-valuemin="0" aria-valuemax="100">
                                <span data-width="{{ $overtimePercent }}"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="att-card">
                    <div class="att-card-head">
                        <h5 class="att-card-title"><i class="fa fa-clock"></i> Today activity</h5>
                        <span class="att-card-sub">{{ \Carbon\Carbon::today()->format('d M') }}</span>
                    </div>
                    <div class="att-card-body">
                        <ul class="att-timeline">
                            @if ($todayAttendance && $todayAttendance->clock_in)
                            <li>
                                <p class="att-event">Punch in</p>
                                <p class="att-time">
                                    <i class="fa fa-clock-o"></i>
                                    {{ \Carbon\Carbon::parse($todayAttendance->clock_in)->format('h:i A') }}
                                </p>
                            </li>
                            @if ($todayAttendance->break_start)
                            <li>
                                <p class="att-event">Break start</p>
                                <p class="att-time">
                                    <i class="fa fa-clock-o"></i>
                                    {{ \Carbon\Carbon::parse($todayAttendance->break_start)->format('h:i A') }}
                                </p>
                            </li>
                            @endif
                            @if ($todayAttendance->break_end)
                            <li>
                                <p class="att-event">Break end</p>
                                <p class="att-time">
                                    <i class="fa fa-clock-o"></i>
                                    {{ \Carbon\Carbon::parse($todayAttendance->break_end)->format('h:i A') }}
                                </p>
                            </li>
                            @endif
                            @if ($todayAttendance->clock_out)
                            <li>
                                <p class="att-event">Punch out</p>
                                <p class="att-time">
                                    <i class="fa fa-clock-o"></i>
                                    {{ \Carbon\Carbon::parse($todayAttendance->clock_out)->format('h:i A') }}
                                </p>
                            </li>
                            @endif
                            @else
                            <li>
                                <p class="att-event">No activity yet</p>
                                <p class="att-time">Clock in from the timesheet to start today's record.</p>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="att-card mb-3">
            <div class="att-card-body">
                <form id="filterForm" method="GET" action="{{ route('attendance.index') }}" class="att-filter">
                    <div class="att-filter-head">
                        <span class="att-filter-title"><i class="fa fa-filter"></i> Filters</span>
                        <span class="att-filter-hint">narrows the history below · opens on this month</span>

                        <div class="ms-auto d-flex gap-2">
                            <a href="{{ route('attendance.index') }}" class="btn btn-sm btn-light"
                                title="Back to the current month">Reset</a>
                            <button type="submit" class="btn btn-sm btn-brand">
                                <i class="fa fa-check"></i> Apply filters
                            </button>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label" for="attDate">Date</label>
                            <input type="date" name="date" id="attDate" value="{{ request('date') }}"
                                class="form-control form-control-sm">
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label" for="attMonth">Month</label>
                            <select class="form-select form-select-sm" name="month" id="attMonth">
                                <option value="">All months</option>
                                @foreach (range(1, 12) as $monthOption)
                                <option value="{{ $monthOption }}" @selected($month===$monthOption)>
                                    {{ \Carbon\Carbon::create()->month($monthOption)->format('F') }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label" for="attYear">Year</label>
                            <select class="form-select form-select-sm" name="year" id="attYear">
                                <option value="">All years</option>
                                @foreach (range(date('Y') - 5, date('Y')) as $yearOption)
                                <option value="{{ $yearOption }}" @selected($year===$yearOption)>
                                    {{ $yearOption }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- /Filters -->

        <div class="row">
            <div class="col-lg-12">
                <div class="att-card">
                    <div class="att-card-head">
                        <h5 class="att-card-title"><i class="fa fa-history"></i> Attendance history</h5>
                        @if ($attendances->total())
                        <span class="att-card-sub">
                            {{ $period ? $period . ' · ' : '' }}
                            {{ $attendances->total() }} {{ \Illuminate\Support\Str::plural('record', $attendances->total()) }}
                        </span>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table att-table">
                            <thead>
                                <tr>
                                    <th style="width: 56px;">No</th>
                                    <th>Date</th>
                                    <th>Clock in</th>
                                    <th>Clock out</th>
                                    <th class="text-end">Production</th>
                                    <th class="text-end">Break</th>
                                    <th class="text-end">Overtime</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($attendances as $index => $attendance)
                                @php $day = \Carbon\Carbon::parse($attendance->date); @endphp
                                <tr>
                                    <td class="text-muted">{{ $attendances->firstItem() + $index }}</td>
                                    <td>
                                        {{ $day->format('d M Y') }}
                                        <span class="att-day">{{ $day->format('l') }}</span>
                                    </td>
                                    <td>
                                        @if ($attendance->clock_in)
                                        {{ \Carbon\Carbon::parse($attendance->clock_in)->format('h:i A') }}
                                        @else
                                        <span class="text-muted">No punch</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($attendance->clock_out)
                                        {{ \Carbon\Carbon::parse($attendance->clock_out)->format('h:i A') }}
                                        @else
                                        <span class="text-muted">No punch</span>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $attendance->total_hours, 2) }} hrs</td>
                                    <td class="text-end">{{ number_format((float) $attendance->break_hours, 2) }} hrs</td>
                                    <td class="text-end">{{ number_format((float) $attendance->overtime_hours, 2) }} hrs</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7">
                                        <div class="att-empty">
                                            <i class="fa fa-calendar-times"></i>
                                            <strong>
                                                {{ $period ? 'No attendance records for ' . $period : 'No attendance records yet' }}
                                            </strong>
                                            <p>
                                                {{ $hasFilter
                                                    ? 'Try another month or year, or open the full history.'
                                                    : 'Once you clock in, every day shows up here with its hours.' }}
                                            </p>
                                            @if ($hasFilter)
                                            <a href="{{ route('attendance.index', ['date' => '', 'month' => '', 'year' => '']) }}"
                                                class="btn btn-sm btn-brand-outline">
                                                Show full history
                                            </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($attendances->total())
                    <div class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-top">
                        <span class="att-total">
                            Showing {{ $attendances->firstItem() }}–{{ $attendances->lastItem() }}
                            of {{ $attendances->total() }}
                        </span>

                        @if ($attendances->hasPages())
                        <nav class="ms-auto att-pager" aria-label="Attendance history pages">
                            <ul class="pagination pagination-sm">
                                <li class="page-item {{ $attendances->onFirstPage() ? 'disabled' : '' }}">
                                    <a class="page-link" href="{{ $attendances->previousPageUrl() ?: '#' }}"
                                        rel="prev" aria-label="Previous page">&laquo;</a>
                                </li>
                                @foreach ($attendances->getUrlRange(max(1, $attendances->currentPage() - 1), min($attendances->lastPage(), $attendances->currentPage() + 1)) as $page => $url)
                                <li class="page-item {{ $page === $attendances->currentPage() ? 'active' : '' }}">
                                    <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                                </li>
                                @endforeach
                                <li class="page-item {{ $attendances->hasMorePages() ? '' : 'disabled' }}">
                                    <a class="page-link" href="{{ $attendances->nextPageUrl() ?: '#' }}"
                                        rel="next" aria-label="Next page">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                        @endif
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!-- /Page Content -->
</div>

{{-- ============================================================ --}}
{{-- QR MODALS — Training-topics style                             --}}
{{-- ============================================================ --}}
@foreach([
['id' => 'qrInModal', 'type' => 'in', 'qr' => $checkInQr, 'label' => 'Check-In'],
['id' => 'qrOutModal', 'type' => 'out', 'qr' => $checkOutQr, 'label' => 'Check-Out'],
] as $modal)
<div class="modal fade" id="{{ $modal['id'] }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 350px;">
        <div class="modal-content border-0 shadow" style="border-radius: 8px;">

            <div class="modal-body text-center p-4">
                @if($modal['qr'] && $modal['qr']->isImage())

                <div class="mb-3 d-flex justify-content-center">
                    <img src="{{ $modal['qr']->getQrDataUrl() }}"
                        alt="{{ $modal['label'] }} QR Code"
                        style="width: 200px; height: 200px; object-fit: contain;">
                </div>

                <p class="text-muted mb-3" style="font-size: 13px;">
                    Scan to {{ $modal['label'] === 'Check-In' ? 'clock in' : 'clock out' }} for Attendance
                </p>

                <div class="input-group mb-3">
                    @php($qrId = 'url' . $modal['id'])
                    <input type="text" class="form-control form-control-sm text-muted bg-light"
                        value="{{ $modal['qr']->signed_url }}" readonly
                        style="font-size: 12px;" id="{{ $qrId }}">
                    <button class="btn btn-outline-secondary bg-light" type="button"
                        data-copy-target="{{ $qrId }}" title="Copy the link">
                        <i class="far fa-copy"></i>
                    </button>
                </div>

                <!-- Changed from Red to Brand Color -->
                <a href="{{ $modal['qr']->signed_url }}" target="_blank" class="btn btn-brand-modal w-100">
                    <i class="fas fa-external-link-alt"></i> Open Direct Link
                </a>

                @elseif($modal['qr'] && $modal['qr']->isHtml())
                <div class="alert alert-info">
                    <i class="fas fa-link"></i> Fallback link:
                    <a href="{{ $modal['qr']->signed_url }}" target="_blank" class="text-break">
                        {{ $modal['qr']->signed_url }}
                    </a>
                </div>
                @else
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> QR code not generated yet.
                </div>
                @endif
            </div>

            <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>

        </div>
    </div>
</div>
@endforeach

{{-- JAVASCRIPT --}}
<script>
    // 0. STATISTICS BARS — sized from data-width, so no Blade sits in a style attribute
    Array.prototype.forEach.call(document.querySelectorAll('.att-bar > span[data-width]'), function(bar) {
        var width = parseFloat(bar.dataset.width);

        if (!isNaN(width)) {
            bar.style.width = Math.max(0, Math.min(100, width)) + '%';
        }
    });

    // 0b. FILTERS — a date is precise on its own, so drop the month/year
    // selections the server would ignore anyway.
    var attDateField = document.getElementById('attDate');
    var attMonthField = document.getElementById('attMonth');
    var attYearField = document.getElementById('attYear');

    if (attDateField) {
        attDateField.addEventListener('change', function() {
            if (!this.value) return;
            if (attMonthField) attMonthField.value = '';
            if (attYearField) attYearField.value = '';
        });
    }

    // 1. NATIVE JS CLOCK
    let serverNow = new Date("{{ now()->toIso8601String() }}");

    function updateLiveTime() {
        serverNow.setSeconds(serverNow.getSeconds() + 1);
        const liveClock = document.getElementById('liveWorkedHours');
        if (liveClock) {
            liveClock.textContent = serverNow.toLocaleTimeString('en-US', {
                hour12: true
            });
        }
    }
    setInterval(updateLiveTime, 1000);
    updateLiveTime();

    // 2. GPS COLLECTION ONLY — the server decides whether you are inside the geofence
    function collectDutyNote(prefix) {
        const reason = (document.getElementById('dutyReason')?.value || '').trim();
        const text = (document.getElementById('dutyText')?.value || '').trim();

        let note = '';
        if (reason && text) note = reason + ' - ' + text;
        else if (reason) note = reason;
        else note = text;

        const field = document.getElementById(prefix + 'DutyNote');
        if (field) field.value = note;
    }

    function handleClock(type, btn) {
        if (!navigator.geolocation) {
            alert("Geolocation is not supported by your browser.");
            return;
        }

        // Offline there is no network fix and no punch: say it now instead of
        // waiting out both GPS attempts.
        if (navigator.onLine === false) {
            alert("This device is offline. Connect to the internet and try again.");
            return;
        }

        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Locating...';
        btn.disabled = true;

        const prefix = (type === 'in') ? 'clockIn' : 'clockOut';

        locate(
            position => {
                document.getElementById(prefix + 'Latitude').value = position.coords.latitude;
                document.getElementById(prefix + 'Longitude').value = position.coords.longitude;
                document.getElementById(prefix + 'Accuracy').value = position.coords.accuracy || 0;

                // Reason for HR when this punch is not at the assigned site.
                collectDutyNote(prefix);

                // Submit the raw GPS reading. The GeofenceService compares it with your
                // assigned location and returns the measured distance if you are outside.
                document.getElementById(prefix + 'Form').submit();
            },
            error => {
                let msg = 'Location Error.';
                if (error && error.code === 1) msg = 'Permission denied. Please allow location access.';
                if (error && error.code === 2) msg = 'Position unavailable. Turn Location on and try again.';
                if (error && error.code === 3) msg = 'Could not get a location fix. Step outside or near a window, or connect to the internet, and try again.';

                alert(msg);
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        );
    }

    // A GPS chip needs open sky, so the first pass regularly times out indoors.
    // The second pass asks for a network (Wi-Fi / cell) fix instead: fast indoors,
    // and the server accepts coarse readings up to config('attendance.max_accuracy_m').
    // A fix up to 30 s old is reused first, which is the one the OS usually has.
    const geoAttempts = [{
        enableHighAccuracy: true,
        timeout: 12000,
        maximumAge: 30000
    }, {
        enableHighAccuracy: false,
        timeout: 20000,
        maximumAge: 120000
    }];

    function locate(onPosition, onError) {
        let attempt = 0;

        const tryNext = function(lastError) {
            if (attempt >= geoAttempts.length) {
                onError(lastError);
                return;
            }

            navigator.geolocation.getCurrentPosition(onPosition, function(error) {
                // A denied permission is final: retrying only re-prompts.
                if (error.code === 1) {
                    onError(error);
                    return;
                }

                tryNext(error);
            }, geoAttempts[attempt++]);
        };

        tryNext(null);
    }

    function copyQrUrl(id) {
        const input = document.getElementById(id);
        if (!input) return;
        input.select();
        navigator.clipboard.writeText(input.value).then(() => {
            if (window.Toastr) Toastr.success('URL copied');
        });
    }

    // The copy buttons on the QR modals name their target instead of carrying
    // an inline handler, so no Blade sits inside an onclick attribute.
    Array.prototype.forEach.call(document.querySelectorAll('[data-copy-target]'), function(button) {
        button.addEventListener('click', function() {
            copyQrUrl(button.dataset.copyTarget);
        });
    });
</script>

@endsection
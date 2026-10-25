@extends('layouts.master')
@section('content')
@php
// Month range shown on screen; used to pre-scope the report links.
$periodStart = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1);
$periodEnd = $periodStart->copy()->endOfMonth();

// The user report route is Admin|Super Admin|HR only, so names are
// linked only for viewers who can actually open it (or their own row).
$canViewReports = auth()->user() && auth()->user()->hasAnyRole(['Admin', 'Super Admin', 'HR']);
@endphp

<style>
    /* ---------- Filter bar: every control on one row from lg up ---------- */
    .att-filter {
        border: 0;
        box-shadow: 0 .125rem .375rem rgba(0, 0, 0, .06);
    }

    .att-filter .form-label {
        font-size: .72rem;
        font-weight: 600;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #6c757d;
        margin-bottom: .25rem;
        white-space: nowrap;
    }

    .att-filter .form-control,
    .att-filter .form-select {
        height: 38px;
    }

    .att-filter .btn {
        height: 38px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }

    /* Searchable department dropdown (select2 ships with the layout). */
    .att-filter .select2-container {
        width: 100% !important;
    }

    .att-filter .select2-container--default .select2-selection--single {
        height: 38px;
        padding: .375rem .75rem;
        border: 1px solid #dee2e6;
        border-radius: .375rem;
    }

    .att-filter .select2-container--default .select2-selection--single .select2-selection__rendered {
        padding: 0 1.25rem 0 0;
        line-height: 1.5;
        color: #212529;
    }

    .att-filter .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 36px;
        right: .375rem;
    }

    .att-filter .select2-container--default .select2-selection--single .select2-selection__clear {
        height: 36px;
        margin-right: .5rem;
        line-height: 2.2;
    }

    .att-filter .select2-container--default.select2-container--focus .select2-selection--single,
    .att-filter .select2-container--default.select2-container--open .select2-selection--single {
        border-color: #86b7fe;
        box-shadow: 0 0 0 .25rem rgba(13, 110, 253, .25);
    }

    /* ---------- Attendance sheet: freeze S.No. + Employee ---------- */
    /* Only the day columns move when the sheet is scrolled sideways.
       The header band mirrors /employee-details (its index.blade.php sets
       the same mint background and teal text for .custom-table th); the
       500 weight comes from style.css (.table thead th). */
    .att-sheet thead th {
        background-color: #ecf4f2;
        color: #395750;
    }

    .att-sheet tbody td {
        text-align: center;
    }

    .att-sheet tbody td.att-freeze-name {
        text-align: left;
    }

    .att-freeze {
        position: sticky;
        z-index: 2;
        background-color: #fff;
    }

    /* Sticky header cells must be opaque, or the day columns show through
       when the sheet scrolls sideways. Same mint as the rest of the band. */
    .att-sheet thead .att-freeze {
        z-index: 3;
        background-color: #ecf4f2;
    }

    .att-freeze-1 {
        left: 0;
        width: 64px;
        min-width: 64px;
        max-width: 64px;
    }

    .att-freeze-2 {
        left: 64px;
        width: 250px;
        min-width: 250px;
        max-width: 250px;
        box-shadow: inset -1px 0 0 #dee2e6, 4px 0 6px -4px rgba(0, 0, 0, .25);
    }

    /* One row per employee, never wrapped. Qualified because .table-nowrap td
       in style.css outranks a bare .att-freeze-2. */
    .att-sheet td.att-freeze-2,
    .att-sheet th.att-freeze-2 {
        white-space: nowrap;
    }

    .att-sheet td.att-freeze-2 {
        overflow: hidden;
    }

    /* Name shrinks with an ellipsis (full value lives in the tooltip) so the
       pinned column keeps its width and the status badge never gets clipped. */
    .att-name-row {
        display: flex;
        align-items: center;
        gap: .35rem;
        min-width: 0;
    }

    .att-name-text,
    .att-name-text a {
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        color: #395750;
        font-weight: 600;
        text-decoration: none;
    }

    .att-name-text a:hover {
        text-decoration: underline;
    }

    .att-name-row .badge {
        flex: 0 0 auto;
    }

    /* the project stylesheet colours row hover on <tr>, which a white frozen
       cell would paint over */
    .att-sheet.table-hover>tbody>tr:hover>.att-freeze {
        background-color: #f7f7f7;
    }

    .att-sheet thead th:not(.att-freeze),
    .att-sheet tbody td:not(.att-freeze) {
        min-width: 36px;
    }

    /* On a phone the frozen block must not swallow the whole screen. */
    @media (max-width: 575.98px) {
        .att-freeze-2 {
            width: 170px;
            min-width: 170px;
            max-width: 170px;
        }

        .att-sheet thead th:not(.att-freeze),
        .att-sheet tbody td:not(.att-freeze) {
            min-width: 32px;
        }
    }

    /* ---------- Attendance detail modal ---------- */
    /* Branded on the sidebar's forest green: the header carries the person's
       identity, while the body stays on pale surfaces so the two punch times
       remain the loudest thing on screen. */
    #attendanceModal .modal-dialog {
        max-width: 1040px;
    }

    #attendanceModal .modal-content {
        border: 0;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 24px 60px rgba(16, 26, 24, .28);
    }

    #attendanceModal .modal-header {
        align-items: center;
        gap: 1rem;
        padding: 1.15rem 1.5rem;
        border-bottom: 0;
        color: #fff;
        background: linear-gradient(135deg, #1c2b28 0%, #395750 100%);
    }

    #attendanceModal .att-avatar {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 58px;
        width: 58px;
        height: 58px;
        border-radius: 50%;
        border: 1px solid rgba(255, 255, 255, .3);
        background-color: rgba(255, 255, 255, .14);
        font-size: 1.1rem;
        font-weight: 700;
        letter-spacing: .02em;
        color: #fff;
    }

    #attendanceModal .att-identity {
        min-width: 0;
        margin-right: auto;
    }

    #attendanceModal .att-name {
        margin: 0;
        font-size: 1.4rem;
        font-weight: 700;
        line-height: 1.2;
        color: #fff;
    }

    #attendanceModal .att-meta {
        margin: .15rem 0 0;
        font-size: .8rem;
        color: rgba(255, 255, 255, .72);
    }

    /* The header is dark, so the close control is inverted to stay visible. */
    #attendanceModal .modal-header .btn-close {
        filter: invert(1) grayscale(1) brightness(2);
        opacity: .85;
    }

    #attendanceModal .modal-header .btn-close:hover {
        opacity: 1;
    }

    #attendanceModal .att-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .3rem .7rem;
        border-radius: 999px;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .05em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    #attendanceModal .att-chip::before {
        content: "";
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: currentColor;
    }

    #attendanceModal .att-chip--done {
        background-color: #d7efe3;
        color: #16452f;
    }

    #attendanceModal .att-chip--working {
        background-color: #f8e7bd;
        color: #6b4b00;
    }

    #attendanceModal .att-chip--missing {
        background-color: #f4cccc;
        color: #7a2222;
    }

    #attendanceModal .modal-body {
        padding: 1.5rem;
        background-color: #f7f9f8;
    }

    #attendanceModal .att-tiles {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }

    #attendanceModal .att-tile {
        display: flex;
        align-items: flex-start;
        gap: .9rem;
        padding: 1rem 1.1rem;
        border: 1px solid #e2e8e6;
        border-radius: 14px;
        background-color: #fff;
    }

    #attendanceModal .att-tile-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        flex: 0 0 46px;
        width: 46px;
        height: 46px;
        border-radius: 12px;
        background-color: #1c2b28;
        color: #fff;
        font-size: 1rem;
    }

    #attendanceModal .att-tile-label {
        margin-bottom: .1rem;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #6b7b77;
    }

    #attendanceModal .att-tile-value {
        margin: 0;
        font-size: 1.5rem;
        font-weight: 700;
        line-height: 1.15;
        color: #1c2b28;
    }

    /* A status is a sentence, not a timestamp: same emphasis, smaller size. */
    #attendanceModal .att-tile-value.is-status {
        font-size: 1.05rem;
        line-height: 1.6;
    }

    #attendanceModal .att-tile-value.is-warning {
        color: #8a5a00;
    }

    #attendanceModal .att-tile-value.is-danger {
        color: #7a2222;
    }

    #attendanceModal .att-tile-note {
        margin: .2rem 0 0;
        font-size: .75rem;
        color: #7a8a86;
    }

    #attendanceModal .att-stats {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .75rem;
        margin-top: 1rem;
    }

    #attendanceModal .att-stat {
        padding: .8rem .9rem;
        border: 1px solid #e2e8e6;
        border-radius: 12px;
        background-color: #fff;
        text-align: center;
    }

    #attendanceModal .att-stat-label {
        display: block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #6b7b77;
    }

    #attendanceModal .att-stat-value {
        display: block;
        margin-top: .15rem;
        font-size: 1.15rem;
        font-weight: 700;
        color: #1c2b28;
    }

    #attendanceModal .att-stat-note {
        display: block;
        margin-top: .1rem;
        font-size: .68rem;
        color: #8a9995;
    }

    #attendanceModal .modal-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid #e2e8e6;
        background-color: #fff;
    }

    #attendanceModal .btn-brand-solid {
        background-color: #1c2b28;
        border-color: #1c2b28;
        color: #fff;
        font-weight: 600;
    }

    #attendanceModal .btn-brand-solid:hover {
        background-color: #395750;
        border-color: #395750;
        color: #fff;
    }

    @media (max-width: 575.98px) {
        #attendanceModal .modal-header {
            padding: 1rem;
        }

        #attendanceModal .att-avatar {
            flex: 0 0 46px;
            width: 46px;
            height: 46px;
            font-size: .95rem;
        }

        #attendanceModal .att-name {
            font-size: 1.15rem;
        }

        #attendanceModal .modal-body {
            padding: 1rem;
        }

        #attendanceModal .att-tiles {
            grid-template-columns: minmax(0, 1fr);
        }

        #attendanceModal .att-stats {
            gap: .5rem;
        }

        #attendanceModal .att-stat {
            padding: .6rem .35rem;
        }

        #attendanceModal .att-stat-label {
            font-size: .62rem;
            letter-spacing: .03em;
        }
    }
</style>

<!-- Page Wrapper -->
<div class="page-wrapper">
    <div class="content container-fluid">
        <!-- Main Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0" style="color: #395750;">Attendance Sheet</h4>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    {{-- The sheet lists active accounts only; deactivated people are
                         shown on request (they are not expected to punch anymore). --}}
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="attShowInactive"
                            {{ $includeInactive ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="attShowInactive" style="color: #395750;">
                            Show inactive
                        </label>
                    </div>
                    <span class="text-muted">{{ date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)) }}</span>
                </div>
            </div>
            <div class="card-body">
                <!-- Filter Section -->
                <div class="card mb-4 att-filter">
                    <div class="card-body py-3">
                        <form action="{{ route('attendance.page') }}" method="GET" id="attFilterForm">
                            {{-- Kept in sync by the "Show inactive" switch in the card
                                 header, so submitting the filters keeps the choice. --}}
                            <input type="hidden" name="include_inactive" id="attIncludeInactive"
                                value="{{ $includeInactive ? '1' : '' }}">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label" for="attEmployeeName">Employee Name</label>
                                    <input type="text" name="employee_name" id="attEmployeeName" class="form-control"
                                        placeholder="Search by name..." value="{{ request('employee_name') }}">
                                </div>
                                <div class="col-12 col-sm-6 col-lg-3">
                                    <label class="form-label" for="attDepartment">Department</label>
                                    <select name="department" id="attDepartment" class="form-select att-dept">
                                        <option value="">All Departments</option>
                                        @foreach ($departments as $dept)
                                        <option value="{{ $dept->id }}" {{ $departmentId == $dept->id ? 'selected' : '' }}>
                                            {{ $dept->department_name }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-sm-3 col-lg-2">
                                    <label class="form-label" for="attMonth">Month</label>
                                    <select name="month" id="attMonth" class="form-select">
                                        @foreach (range(1, 12) as $month)
                                        <option value="{{ $month }}" {{ $selectedMonth == $month ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-sm-3 col-lg-2">
                                    <label class="form-label" for="attYear">Year</label>
                                    <select name="year" id="attYear" class="form-select">
                                        @foreach (range(date('Y') - 5, date('Y')) as $year)
                                        <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                                            {{ $year }}
                                        </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-lg-2 d-flex gap-2">
                                    <button type="submit" class="btn text-white flex-fill"
                                        style="background-color: #395750;">
                                        <i class="fas fa-search me-1"></i>Filter
                                    </button>
                                    <a href="{{ route('attendance.page') }}" class="btn text-white flex-fill"
                                        style="background-color: #6b8f86;">
                                        <i class="fas fa-redo me-1"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Table -->
                @php
                // The "today" column is only meaningful for the current month.
                $todayDay = ($selectedYear == now()->year && $selectedMonth == now()->month) ? now()->day : null;
                // Carried into every day cell so the modal can tell an open
                // shift (still working) from a missing punch-out record.
                $todayDate = now()->toDateString();
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered table-hover custom-table table-nowrap mb-0 att-sheet">
                        <thead class="text-white" style="background-color: #395750;">
                            <tr>
                                <th class="att-freeze att-freeze-1">S.No.</th>
                                <th class="att-freeze att-freeze-2">Employee</th>

                                @for ($day = 1; $day <= $daysInMonth; $day++)
                                    <th class="text-center"
                                    @if ($day===$todayDay) style="box-shadow: inset 0 -3px 0 #ffc107;" title="Today" @endif>
                                    {{ $day }}
                                    </th>
                                    @endfor
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($employees as $employee)
                            {{-- The report link is carried once per row (31 cells would
                                 mean 31 route() calls) and copied into the modal. --}}
                            <tr data-employee="{{ $employee->name }}"
                                @if ($canViewReports) data-report-url="{{ route('attendance.reports.user', ['user' => $employee->id, 'from' => $periodStart->toDateString(), 'to' => $periodEnd->toDateString()]) }}" @endif>
                                <td class="att-freeze att-freeze-1">{{ $loop->iteration }}</td>
                                <td class="att-freeze att-freeze-2 att-freeze-name">
                                    <div class="att-name-row">
                                        <span class="att-name-text" title="{{ $employee->name }}">
                                            @if ($canViewReports || auth()->id() === $employee->id)
                                            <a href="{{ route('attendance.reports.user', ['user' => $employee->id, 'from' => $periodStart->toDateString(), 'to' => $periodEnd->toDateString()]) }}"
                                                title="View {{ $employee->name }}'s attendance report">{{ $employee->name }}</a>
                                            @else
                                            {{ $employee->name }}
                                            @endif
                                        </span>
                                        {{-- Kept on the sheet so history is not lost, but flagged:
                                             an inactive account is not expected to punch. --}}
                                        @if (strtolower((string) $employee->status) !== 'active')
                                        <span class="badge bg-secondary"
                                            title="Inactive account — absences after deactivation are not real">{{ $employee->status ?: 'Inactive' }}</span>
                                        @endif
                                    </div>
                                </td>
                                @for ($day = 1; $day <= $daysInMonth; $day++)
                                    @php
                                    $today=$employee->attendanceData[$day] ?? null;
                                    $details = $today['details'] ?? [];
                                    @endphp

                                    <td>
                                        @if ($today && in_array($today['status'], ['present', 'saturday_present'], true))
                                        <a href="#" class="show-attendance"
                                            data-id="{{ $employee->attendanceData[$day]['details']['id'] ?? '' }}"
                                            data-date="{{ $details['date'] }}"
                                            data-date-label="{{ \Carbon\Carbon::parse($details['date'])->format('D, d M Y') }}"
                                            {{-- Absent values stay empty so the modal can say what
                                                 actually happened instead of printing "N/A". --}}
                                            data-is-today="{{ $details['date'] === $todayDate ? '1' : '0' }}"
                                            data-clockin="{{ $details['clock_in'] ?? '' }}"
                                            data-clockout="{{ $details['clock_out'] ?? '' }}"
                                            data-break="{{ $details['break_hours'] }}"
                                            data-total="{{ $details['total_hours'] }}"
                                            data-overtime="{{ $details['overtime_hours'] ?? 0 }}"
                                            data-accuracy-in="{{ $details['clock_in_accuracy_m'] ?? '' }}"
                                            data-accuracy-out="{{ $details['clock_out_accuracy_m'] ?? '' }}"
                                            data-clockin-image="{{ $details['clock_in_image'] }}"
                                            data-clockout-image="{{ $details['clock_out_image'] }}"
                                            data-bs-toggle="modal" data-bs-target="#attendanceModal">
                                            <i class="fa fa-check text-success"
                                                title="{{ $today['status'] === 'saturday_present' ? 'Saturday clock-in' : 'Recorded' }}"></i>
                                        </a>
                                        @elseif ($today && $today['status'] === 'sunday')
                                        <span class="text-info" title="Sunday — not a working day">Su</span>
                                        @elseif ($today && $today['status'] === 'holiday')
                                        <span class="text-primary" title="Public holiday — not counted as absent">H</span>
                                        @elseif ($today && $today['status'] === 'leave')
                                        <span class="text-warning"
                                            title="Approved leave ({{ ($details['leave_weight'] ?? 1) < 1 ? 'half day' : 'full day' }}) — not counted as absent">L</span>
                                        @elseif ($today && $today['status'] === 'future')
                                        <span class="text-muted" title="Upcoming day — not yet due">&middot;</span>
                                        @elseif ($today && $today['status'] === 'before_employment')
                                        <span class="text-muted"
                                            title="Before the joining date ({{ $employee->joined_on ?? 'unknown' }})">&ndash;</span>
                                        @else
                                        <i class="fa fa-times text-danger" title="Absent — a working day with no punch"></i>
                                        @endif
                                    </td>
                                    @endfor
                            </tr>
                            @endforeach


                        </tbody>

                    </table>
                </div>
                {{-- Legend lives outside .table-responsive so it does not slide
                     away with the day columns. --}}
                <div class="d-flex flex-wrap gap-3 small text-muted px-2 py-2 border-top">
                    <span><i class="fa fa-check text-success"></i> recorded</span>
                    <span><span class="text-info">Su</span> Sunday</span>
                    <span><span class="text-primary">H</span> public holiday</span>
                    <span><span class="text-warning">L</span> approved leave</span>
                    <span><span class="text-muted">&middot;</span> upcoming</span>
                    <span><span class="text-muted">&ndash;</span> before joining</span>
                    <span><i class="fa fa-times text-danger"></i> absent</span>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="attendanceModal" tabindex="-1" role="dialog" aria-labelledby="attendanceModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header flex-wrap">
                    {{-- Initials stand in for an avatar: the sheet carries no photo. --}}
                    <div class="att-avatar" id="modal-avatar" aria-hidden="true"></div>
                    <div class="att-identity">
                        <h5 class="att-name" id="attendanceModalLabel">Attendance Details</h5>
                        <p class="att-meta" id="modal-date"></p>
                    </div>
                    {{-- Replaced by JS with Completed / Still working / Missing punch-out. --}}
                    <span class="att-chip att-chip--done" id="modal-status">Completed</span>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="att-tiles">
                        <div class="att-tile">
                            <div class="att-tile-icon"><i class="fa fa-sign-in-alt"></i></div>
                            <div class="flex-grow-1">
                                <div class="att-tile-label">Clock in</div>
                                <p class="att-tile-value" id="modal-clockin"></p>
                                <p class="att-tile-note" id="modal-clockin-note"></p>
                            </div>
                        </div>
                        <div class="att-tile">
                            <div class="att-tile-icon"><i class="fa fa-sign-out-alt"></i></div>
                            <div class="flex-grow-1">
                                <div class="att-tile-label">Clock out</div>
                                <p class="att-tile-value" id="modal-clockout"></p>
                                <p class="att-tile-note" id="modal-clockout-note"></p>
                            </div>
                        </div>
                    </div>

                    <div class="att-stats">
                        <div class="att-stat">
                            <span class="att-stat-label">Worked</span>
                            <span class="att-stat-value" id="modal-worked">0 hrs</span>
                            <span class="att-stat-note" id="modal-worked-note"></span>
                        </div>
                        <div class="att-stat">
                            <span class="att-stat-label">Break</span>
                            <span class="att-stat-value" id="modal-break">0 hrs</span>
                        </div>
                        <div class="att-stat">
                            <span class="att-stat-label">Overtime</span>
                            <span class="att-stat-value" id="modal-overtime">0 hrs</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {{-- /attendance/reports/user/{id} is role-gated, so the button is
                         only rendered for viewers who can actually open it. --}}
                    <a href="#" id="modal-report-link"
                        class="btn btn-brand-solid me-auto {{ $canViewReports ? '' : 'd-none' }}">
                        <i class="fas fa-chart-line me-1"></i> Full month report
                    </a>
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <!-- /Attendance Modal -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Every lookup is optional: the sheet only opens this modal on a
            // recorded day, but a missing node must never leave the rest of the
            // fields unset. Missing values read as prose, never as "N/A".
            const setText = function(id, value) {
                const el = document.getElementById(id);
                if (el) {
                    el.textContent = value;
                }
            };

            // "1 hrs" read badly; hours are stored as decimals (0.5, 8.37).
            const hours = function(value) {
                const n = parseFloat(value || 0) || 0;
                return n + (n === 1 ? ' hr' : ' hrs');
            };

            // Two initials are all that fit in the avatar circle.
            const initials = function(name) {
                const parts = (name || '').trim().split(/\s+/).filter(Boolean);
                if (!parts.length) {
                    return '';
                }
                const last = parts.length > 1 ? parts[parts.length - 1][0] : '';
                return (parts[0][0] + last).toUpperCase();
            };

            // A punch is accepted up to config('attendance.max_accuracy_m'), so
            // HR can see how sharp the fix behind it actually was. "No reading"
            // is said in words because the number does not exist at all.
            const gpsNote = function(accuracy) {
                const metres = parseFloat(accuracy);
                if (!isFinite(metres) || metres <= 0) {
                    return 'No location reading on this punch';
                }
                return 'Location accuracy \u00b1' + Math.round(metres) + ' m';
            };

            const setStatus = function(modifier, label) {
                const chip = document.getElementById('modal-status');
                if (!chip) {
                    return;
                }
                chip.classList.remove('att-chip--done', 'att-chip--working', 'att-chip--missing');
                chip.classList.add(modifier);
                chip.textContent = label;
            };

            document.querySelectorAll('.show-attendance').forEach(function(link) {
                link.addEventListener('click', function() {
                    // Employee name + report URL live once on the <tr>, not on
                    // each of the 31 day cells.
                    const row = this.closest('tr');
                    const reportUrl = row ? row.dataset.reportUrl : '';
                    const employee = row ? row.dataset.employee || '' : '';
                    const clockIn = (this.dataset.clockin || '').trim();
                    const clockOut = (this.dataset.clockout || '').trim();
                    const isToday = this.dataset.isToday === '1';

                    setText('modal-avatar', initials(employee));
                    setText('attendanceModalLabel', employee || 'Attendance Details');
                    setText('modal-date', this.dataset.dateLabel || this.dataset.date || '');

                    // Clock in — the modal only opens on a punched day, but keep
                    // the fallback honest if a row ever arrives without one.
                    setText('modal-clockin', clockIn || 'No clock-in recorded');
                    const inValue = document.getElementById('modal-clockin');
                    if (inValue) {
                        inValue.classList.toggle('is-status', !clockIn);
                    }
                    setText('modal-clockin-note', clockIn ? gpsNote(this.dataset.accuracyIn) : '');

                    // Clock out — "Still working" only makes sense for today; a
                    // past day without a punch-out is a missing record.
                    const outValue = document.getElementById('modal-clockout');
                    if (clockOut) {
                        setText('modal-clockout', clockOut);
                        setText('modal-clockout-note', gpsNote(this.dataset.accuracyOut));
                        setText('modal-worked-note', 'of an 8 hr day');
                        setStatus('att-chip--done', 'Completed');
                    } else if (isToday) {
                        setText('modal-clockout', 'Not checked out yet');
                        setText('modal-clockout-note', 'This shift is still open');
                        setText('modal-worked-note', 'finalised at clock-out');
                        setStatus('att-chip--working', 'Still working');
                    } else {
                        setText('modal-clockout', 'No punch-out recorded');
                        setText('modal-clockout-note', 'The shift ended without a clock-out');
                        setText('modal-worked-note', 'no clock-out on this day');
                        setStatus('att-chip--missing', 'Missing punch-out');
                    }

                    if (outValue) {
                        outValue.classList.toggle('is-status', !clockOut);
                        outValue.classList.toggle('is-warning', !clockOut && isToday);
                        outValue.classList.toggle('is-danger', !clockOut && !isToday);
                    }

                    setText('modal-worked', hours(this.dataset.total));
                    setText('modal-break', hours(this.dataset.break));
                    setText('modal-overtime', hours(this.dataset.overtime));

                    const report = document.getElementById('modal-report-link');
                    if (report) {
                        report.href = reportUrl || '#';
                        report.classList.toggle('d-none', !reportUrl);
                    }
                });
            });
        });
    </script>


</div>
@endsection

@section('script')
<script>
    $(function() {
        // 25+ departments is too many to scroll, so make the dropdown
        // type-to-search (select2 is loaded by the layout). Guarded on its
        // own: returning early here used to kill the switch below whenever
        // select2 was unavailable.
        if ($.fn.select2) {
            $('#attDepartment').select2({
                width: '100%',
                placeholder: 'All Departments',
                allowClear: true
            });
        }

        // "Show inactive" sits in the card header, outside the filter form,
        // so push the value into its hidden input and resubmit the filters.
        $('#attShowInactive').on('change', function() {
            $('#attIncludeInactive').val(this.checked ? '1' : '');
            $('#attFilterForm').trigger('submit');
        });
    });
</script>
@endsection
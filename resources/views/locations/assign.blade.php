@extends('layouts.master')

@section('content')
@php
$checks = ($unmappedBranches->isNotEmpty() ? 1 : 0)
+ (count($mixedCitySites) ? 1 : 0)
+ (count($duplicateSites) ? 1 : 0);

$rowOffset = function ($paginator) {
return ($paginator->currentPage() - 1) * $paginator->perPage();
};

$tabs = [
'branches' => ['Branch sites', 'fa-building', $stats->branches],
'employees' => ['Employee locations', 'fa-user-tag', $stats->employees],
'postings' => ['Temporary postings', 'fa-calendar-alt', $stats->active_postings],
];
@endphp

<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Assign Attendance Locations</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('attendance.locations.index') }}">Attendance Locations</a></li>
                        <li class="breadcrumb-item active">Assign</li>
                    </ul>
                </div>
                <div class="col-auto d-flex flex-wrap gap-2">
                    <a href="{{ route('attendance.locations.index') }}" class="btn btn-sm btn-light">
                        <i class="fa fa-arrow-left"></i> Back to locations
                    </a>
                </div>
            </div>
        </div>

        @if ($locations->isEmpty())
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="att-empty">
                    <i class="fa fa-map-marker-alt"></i>
                    <h6>No attendance sites yet</h6>
                    <p>Add at least one location before you can map branches or give people a working site.</p>
                    <a href="{{ route('attendance.locations.create') }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-plus"></i> Add location
                    </a>
                </div>
            </div>
        </div>
        @else

        {{-- KPI tiles: one row, straight under the title. --}}
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 g-3 mb-3">
            <div class="col">
                <div class="att-tile{{ $stats->unmapped > 0 ? ' att-tile-alert' : '' }}">
                    <span class="att-tile-label"><i class="fa fa-building"></i> Branches</span>
                    <span class="att-tile-value">{{ $stats->branches }}</span>
                    <span class="att-tile-sub{{ $stats->unmapped > 0 ? ' is-alert' : '' }}">
                        @if ($stats->unmapped > 0)
                        {{ $stats->unmapped }} unmapped
                        @else
                        all mapped
                        @endif
                    </span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-users"></i> People</span>
                    <span class="att-tile-value">{{ $stats->employees }}</span>
                    <span class="att-tile-sub">{{ $stats->with_own_site }} with own site</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-map-marker-alt"></i> Sites</span>
                    <span class="att-tile-value">{{ $stats->sites }}</span>
                    <span class="att-tile-sub">of {{ $stats->total_sites }} total</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-calendar-alt"></i> Postings</span>
                    <span class="att-tile-value" style="color:#1565c0;">{{ $stats->active_postings }}</span>
                    <span class="att-tile-sub">active today</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-link"></i> Coverage</span>
                    <span class="att-tile-value">{{ $stats->mapped_rate === null ? '—' : number_format($stats->mapped_rate, 0) . '%' }}</span>
                    @if ($stats->mapped_rate === null)
                    <span class="att-tile-sub">no branches</span>
                    @else
                    <span class="rate-bar mt-1"><i data-rate="{{ $stats->mapped_rate }}"></i></span>
                    @endif
                </div>
            </div>
            <div class="col">
                <div class="att-tile{{ $fallbackEmployees > 0 ? ' att-tile-alert' : '' }}">
                    <span class="att-tile-label"><i class="fa fa-user-slash"></i> No site</span>
                    <span class="att-tile-value">{{ $fallbackEmployees }}</span>
                    <span class="att-tile-sub">with no working location</span>
                </div>
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs att-tabs mb-3">
            @foreach ($tabs as $key => $meta)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
                    href="{{ route('attendance.locations.assign', ['tab' => $key]) }}">
                    <i class="fa {{ $meta[1] }}"></i> {{ $meta[0] }}
                    <span class="badge ms-1">{{ $meta[2] }}</span>
                </a>
            </li>
            @endforeach
        </ul>

        {{-- ============ TAB 1: BRANCH SITES ============ --}}
        @if ($tab === 'branches')
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('attendance.locations.assign') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="branches">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1" for="brQ">Search</label>
                        <input type="search" name="q" id="brQ" value="{{ $search }}"
                            class="form-control form-control-sm" placeholder="Branch name or city">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1" for="brCity">City</label>
                        <select name="city" id="brCity" class="form-select form-select-sm">
                            <option value="">All cities</option>
                            @foreach ($cities as $city)
                            <option value="{{ $city }}" @selected(strcasecmp($cityFilter, $city)===0)>{{ $city }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1" for="brMapped">Mapping</label>
                        <select name="mapped" id="brMapped" class="form-select form-select-sm">
                            <option value="">Any branch</option>
                            <option value="mapped" @selected($mapped==='mapped' )>Mapped only</option>
                            <option value="unmapped" @selected($mapped==='unmapped' )>Unmapped only</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-filter"></i> Apply</button>
                        <a href="{{ route('attendance.locations.assign', ['tab' => 'branches']) }}"
                            class="btn btn-sm btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header att-card-header py-3">
                <strong>Branch sites</strong>
                <span class="badge ms-1">{{ $branchRows->count() }}</span>
                <div class="small text-muted">Employees inherit their branch site unless they have their own working
                    location.</div>
            </div>
            <form action="{{ route('attendance.locations.assign.branches') }}" method="POST">
                @csrf
                <div class="table-responsive">
                    <table class="table align-middle mb-0 att-table">
                        <thead>
                            <tr>
                                <th class="att-num">#</th>
                                <th>Branch</th>
                                <th>City</th>
                                <th class="text-center">Employees</th>
                                <th style="width: 340px;">Branch site</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($branchRows as $branch)
                            <tr class="{{ $branch->location_id ? '' : 'row-none' }}">
                                <td class="att-num">{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $branch->name }}</div>
                                    @if (!$branch->location_id)
                                    <span class="chip chip-warn">unmapped</span>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $branch->city ?: '—' }}</td>
                                <td class="text-center text-muted">{{ $branch->users_count }}</td>
                                <td>
                                    <select name="branches[{{ $branch->id }}]" class="form-select form-select-sm">
                                        <option value="">— none —</option>
                                        @foreach ($locations as $location)
                                        <option value="{{ $location->id }}"
                                            @disabled(! $location->is_active)
                                            @selected($branch->location_id == $location->id)>
                                            {{ $location->name }}{{ $location->is_active ? '' : ' (inactive)' }}
                                        </option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="p-0">
                                    <div class="att-empty">
                                        <i class="fa fa-search"></i>
                                        <h6>No branch matches these filters</h6>
                                        <p>Try a different city, or clear the mapping filter.</p>
                                        <a href="{{ route('attendance.locations.assign', ['tab' => 'branches']) }}"
                                            class="btn btn-sm btn-brand">Reset filters</a>
                                    </div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($branchRows->isNotEmpty())
                <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <span class="small text-muted">
                        Showing {{ $branchRows->count() }} of {{ $branches->count() }} branch(es)
                    </span>
                    <button type="submit" class="btn btn-sm btn-brand">
                        <i class="fa fa-save"></i> Save branch mapping
                    </button>
                </div>
                @endif
            </form>
        </div>
        @endif

        {{-- ============ TAB 2: EMPLOYEE LOCATIONS ============ --}}
        @if ($tab === 'employees')
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('attendance.locations.assign') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="employees">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1" for="empQ">Search</label>
                        <input type="search" name="q" id="empQ" value="{{ $search }}"
                            class="form-control form-control-sm" placeholder="Employee name or email">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted mb-1" for="empBranch">Branch</label>
                        <select name="branch_id" id="empBranch" class="form-select form-select-sm">
                            <option value="">All branches</option>
                            @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) $branchFilter===(string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1" for="empStatus">Site source</label>
                        <select name="status" id="empStatus" class="form-select form-select-sm">
                            <option value="">Any source</option>
                            <option value="override" @selected($status==='override' )>Branch site only</option>
                            <option value="posting" @selected($status==='posting' )>Temporary posting</option>
                            <option value="fallback" @selected($status==='fallback' )>No site assigned</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-filter"></i> Apply</button>
                        <a href="{{ route('attendance.locations.assign', ['tab' => 'employees']) }}"
                            class="btn btn-sm btn-light">Reset</a>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto text-nowrap"
                            data-bs-toggle="collapse" data-bs-target="#bulkPanel" aria-expanded="false">
                            <i class="fa fa-layer-group"></i> Bulk
                        </button>
                    </div>
                </form>

                {{-- Bulk assign, folded away until needed --}}
                <div class="collapse mt-3" id="bulkPanel">
                    <div class="border-top pt-3">
                        <form action="{{ route('attendance.locations.assign.bulk') }}" method="POST"
                            class="row g-2 align-items-end">
                            @csrf
                            <div class="col-12 col-md-3">
                                <label class="form-label small mb-1" for="bulkScope">Apply to</label>
                                <select name="scope" id="bulkScope" class="form-select form-select-sm">
                                    <option value="branch">Everyone in a branch</option>
                                    <option value="department">Everyone in a department</option>
                                    <option value="unassigned">Everyone without a site</option>
                                </select>
                            </div>
                            <div class="col-6 col-md-3" id="bulkBranchWrap">
                                <label class="form-label small mb-1">Branch</label>
                                <select name="branch_id" class="form-select form-select-sm">
                                    <option value="">— select branch —</option>
                                    @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-3 d-none" id="bulkDeptWrap">
                                <label class="form-label small mb-1">Department</label>
                                <select name="department_id" class="form-select form-select-sm">
                                    <option value="">— select department —</option>
                                    @foreach ($departments as $department)
                                    <option value="{{ $department->id }}">{{ $department->department }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-1">Working location</label>
                                <select name="location_id" class="form-select form-select-sm" required>
                                    <option value="">— select site —</option>
                                    @foreach ($activeLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 col-md-1">
                                <button type="submit" class="btn btn-sm btn-brand w-100">Assign</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header att-card-header py-3">
                <strong>Working locations</strong>
                <span class="badge ms-1">{{ $employees->total() }}</span>
                <div class="small text-muted">A person's own site beats the branch site, and a temporary posting beats
                    both.</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 att-table">
                    <thead>
                        <tr>
                            <th class="att-num">#</th>
                            <th>Employee</th>
                            <th>Branch</th>
                            <th>Site used today</th>
                            <th style="width: 320px;">Working location</th>
                            <th class="text-end">Temporary</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($employees as $employee)
                        @php
                        $resolved = $employee->attendanceLocation();
                        $source = $employee->attendanceLocationSource();
                        $sourceLabel = [
                        'assignment' => ['Temporary posting', 'chip-info'],
                        'user' => ['Own location', 'chip-good'],
                        'branch' => ['Branch site', 'chip-muted'],
                        'none' => ['Not configured', 'chip-none'],
                        ][$source] ?? [$source, 'chip-muted'];
                        $activePosting = $employee->locationAssignments->first(fn ($p) => $p->state === 'active');
                        @endphp
                        <tr>
                            <td class="att-num">{{ $rowOffset($employees) + $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold">{{ $employee->name }}</div>
                                <div class="text-muted small">{{ $employee->email }}</div>
                            </td>
                            <td class="text-muted">{{ $employee->branch->name ?? '—' }}</td>
                            <td>
                                @if ($resolved)
                                <div class="fw-semibold">{{ $resolved->name }}</div>
                                @else
                                <span class="text-danger">Not configured</span>
                                @endif
                                <span class="chip {{ $sourceLabel[1] }}">{{ $sourceLabel[0] }}</span>
                                @if ($employee->location_id && $source !== 'user')
                                <div class="text-muted small">Own: {{ $employee->location->name ?? '—' }}</div>
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('attendance.locations.assign.user') }}" method="POST"
                                    class="d-flex gap-2">
                                    @csrf
                                    <input type="hidden" name="user_id" value="{{ $employee->id }}">
                                    <select name="location_id" class="form-select form-select-sm">
                                        <option value="">— none (use branch) —</option>
                                        @foreach ($locations as $location)
                                        <option value="{{ $location->id }}"
                                            @disabled(! $location->is_active)
                                            @selected($employee->location_id == $location->id)>
                                            {{ $location->name }}{{ $location->is_active ? '' : ' (inactive)' }}
                                        </option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-brand text-nowrap">Save</button>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                @if ($activePosting)
                                <span class="chip chip-info" title="{{ $activePosting->location->name ?? '' }}">
                                    until {{ $activePosting->ends_on?->format('d M') ?? 'set' }}
                                </span>
                                @endif
                                <a href="{{ route('attendance.locations.assign', ['tab' => 'postings', 'user_id' => $employee->id]) }}"
                                    class="btn btn-sm btn-outline-secondary" title="Add a temporary posting">
                                    <i class="fa fa-calendar-plus"></i>
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <div class="att-empty">
                                    <i class="fa fa-user-slash"></i>
                                    <h6>No employee matches these filters</h6>
                                    <p>Try another branch or clear the site-source filter.</p>
                                    <a href="{{ route('attendance.locations.assign', ['tab' => 'employees']) }}"
                                        class="btn btn-sm btn-brand">Reset filters</a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($employees->hasPages())
            <div class="card-footer bg-white">
                {{ $employees->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
        @endif

        {{-- ============ TAB 3: TEMPORARY POSTINGS ============ --}}
        @if ($tab === 'postings')
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('attendance.locations.assign') }}" class="row g-2 align-items-end">
                    <input type="hidden" name="tab" value="postings">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1" for="postQ">Search</label>
                        <input type="search" name="q" id="postQ" value="{{ $search }}"
                            class="form-control form-control-sm" placeholder="Employee name">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1" for="postSite">Site</label>
                        <select name="location_id" id="postSite" class="form-select form-select-sm">
                            <option value="">All sites</option>
                            @foreach ($locations as $location)
                            <option value="{{ $location->id }}" @selected((string) $locationFilter===(string) $location->id)>
                                {{ $location->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1" for="postState">State</label>
                        <select name="posting" id="postState" class="form-select form-select-sm">
                            <option value="">Any state</option>
                            <option value="active" @selected($postingState==='active' )>Active today</option>
                            <option value="scheduled" @selected($postingState==='scheduled' )>Scheduled</option>
                            <option value="expired" @selected($postingState==='expired' )>Expired</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-filter"></i> Apply</button>
                        <a href="{{ route('attendance.locations.assign', ['tab' => 'postings']) }}"
                            class="btn btn-sm btn-light">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header att-card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>New temporary posting</strong>
                    <div class="small text-muted">For a rotation, training or cover duty. When the end date passes the
                        employee automatically goes back to their normal site.</div>
                </div>
            </div>
            <div class="card-body">
                <form action="{{ route('attendance.locations.postings.store') }}" method="POST"
                    class="row g-2 align-items-end">
                    @csrf
                    <div class="col-12 col-md-3">
                        <label class="form-label small mb-1">Employee</label>
                        <select name="user_id" class="form-select form-select-sm" required>
                            <option value="">— select employee —</option>
                            @foreach ($userOptions as $option)
                            <option value="{{ $option->id }}" @selected((string) request('user_id')===(string) $option->id)>
                                {{ $option->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small mb-1">Works at</label>
                        <select name="location_id" class="form-select form-select-sm" required>
                            <option value="">— select site —</option>
                            @foreach ($activeLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">From</label>
                        <input type="date" name="starts_on" class="form-control form-control-sm"
                            value="{{ old('starts_on', now()->toDateString()) }}" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Until <span class="text-muted">(blank = open)</span></label>
                        <input type="date" name="ends_on" class="form-control form-control-sm" value="{{ old('ends_on') }}">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label small mb-1">Note</label>
                        <input type="text" name="note" class="form-control form-control-sm" maxlength="255"
                            value="{{ old('note') }}" placeholder="e.g. cover duty">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-save"></i> Save posting</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header att-card-header py-3">
                <strong>Postings</strong>
                <span class="badge ms-1">{{ $postings->total() }}</span>
                <div class="small text-muted">Dated working-location changes.</div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0 att-table">
                    <thead>
                        <tr>
                            <th class="att-num">#</th>
                            <th>Employee</th>
                            <th>Works at</th>
                            <th>From</th>
                            <th>Until</th>
                            <th>State</th>
                            <th>Note</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($postings as $posting)
                        @php
                        $stateStyles = [
                        'active' => 'chip-good',
                        'scheduled' => 'chip-info',
                        'expired' => 'chip-muted',
                        ];
                        @endphp
                        <tr>
                            <td class="att-num">{{ $rowOffset($postings) + $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold">{{ $posting->user->name ?? 'Deleted user' }}</div>
                                <div class="text-muted small">{{ $posting->user->branch->name ?? '—' }}</div>
                            </td>
                            <td>{{ $posting->location->name ?? '—' }}</td>
                            <td class="text-muted text-nowrap">{{ $posting->starts_on?->format('d M Y') }}</td>
                            <td class="text-muted text-nowrap">{{ $posting->ends_on?->format('d M Y') ?? 'open' }}</td>
                            <td><span class="chip {{ $stateStyles[$posting->state] ?? 'chip-muted' }}">
                                    {{ ucfirst($posting->state) }}</span></td>
                            <td class="text-muted small">{{ $posting->note ?: '—' }}</td>
                            <td class="text-end">
                                <form action="{{ route('attendance.locations.postings.destroy', $posting) }}"
                                    method="POST" onsubmit="return confirm('Remove this posting?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                        title="Remove this posting">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="p-0">
                                <div class="att-empty">
                                    <i class="fa fa-calendar-times"></i>
                                    <h6>No postings match these filters</h6>
                                    <p>Nothing here yet — add one above, or clear the filters.</p>
                                    <a href="{{ route('attendance.locations.assign', ['tab' => 'postings']) }}"
                                        class="btn btn-sm btn-brand">Reset filters</a>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($postings->hasPages())
            <div class="card-footer bg-white">
                {{ $postings->onEachSide(1)->links('pagination::bootstrap-5') }}
            </div>
            @endif
        </div>
        @endif

        {{-- ============ DATA CHECKS (collapsed, bottom of the page) ============ --}}
        @if ($checks > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-header att-card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>Data checks</strong>
                    <span class="badge ms-1">{{ $checks }}</span>
                    <div class="small text-muted">Setups that can send a punch to the wrong site.</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                    data-bs-target="#checksPanel" aria-expanded="false" aria-controls="checksPanel">
                    <i class="fa fa-chevron-down"></i> Show details
                </button>
            </div>
            <div class="collapse" id="checksPanel">
                <div class="card-body">
                    @if ($unmappedBranches->isNotEmpty())
                    <div class="att-check">
                        <div class="att-check-title">
                            <i class="fa fa-exclamation-triangle text-warning"></i>
                            {{ $unmappedBranches->count() }} branch(es) have no site
                        </div>
                        <p class="mb-2">
                            {{ $fallbackEmployees }} employee(s) also have no personal location and no branch site, so
                            there is nothing to geofence them against. Map their branch, or give them a working
                            location in the
                            <a href="{{ route('attendance.locations.assign', ['tab' => 'employees', 'status' => 'fallback']) }}">Employees
                                tab</a>.
                        </p>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($unmappedBranches->take(12) as $branch)
                            <span class="chip chip-warn">{{ $branch->name }}</span>
                            @endforeach
                            @if ($unmappedBranches->count() > 12)
                            <span class="chip chip-muted">+{{ $unmappedBranches->count() - 12 }} more</span>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if (count($mixedCitySites))
                    <div class="att-check">
                        <div class="att-check-title">
                            <i class="fa fa-map-marked-alt text-danger"></i>
                            One site shared by branches in different cities
                        </div>
                        <ul class="mb-0 small">
                            @foreach ($mixedCitySites as $group)
                            <li>
                                <strong>{{ $group['location']->name }}</strong>
                                &larr; {{ implode(', ', array_values($group['cities'])) }}
                                ({{ implode(', ', $group['branches']) }})
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    @if (count($duplicateSites))
                    <div class="att-check">
                        <div class="att-check-title">
                            <i class="fa fa-clone text-warning"></i>
                            Possible duplicate sites
                        </div>
                        <p class="mb-2 small">Two of these split employees across two geofences.</p>
                        <ul class="mb-0 small">
                            @foreach ($duplicateSites as $pair)
                            <li>
                                {{ $pair['a']->name }} and {{ $pair['b']->name }}
                                — {{ $pair['reason'] }}
                                @if ($pair['distance'] !== null)
                                ({{ round($pair['distance']) }} m apart)
                                @endif
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </div>
        </div>
        @endif

        @endif
    </div>
</div>

<script>
    (function() {
        // Coverage bar is sized from data-rate, never from an inline Blade style.
        Array.prototype.forEach.call(document.querySelectorAll('.rate-bar > i[data-rate]'), function(bar) {
            var rate = parseFloat(bar.dataset.rate);

            if (!isNaN(rate)) {
                bar.style.width = Math.max(0, Math.min(100, rate)) + '%';
            }
        });

        var scope = document.getElementById('bulkScope');

        if (scope) {
            var branchWrap = document.getElementById('bulkBranchWrap');
            var deptWrap = document.getElementById('bulkDeptWrap');

            var sync = function() {
                var isDept = scope.value === 'department';
                var isNone = scope.value === 'unassigned';

                branchWrap.classList.toggle('d-none', isDept || isNone);
                deptWrap.classList.toggle('d-none', !isDept);
            };

            scope.addEventListener('change', sync);
            sync();
        }
    })();
</script>

<style>
    /* Tabs */
    .att-report .att-tabs {
        border-bottom: 1px solid #e3ebe8;
    }

    .att-report .att-tabs .nav-link {
        color: #5f7a74;
        font-weight: 600;
        border: 0;
        border-bottom: 2px solid transparent;
        border-radius: 0;
        padding: 10px 14px;
    }

    .att-report .att-tabs .nav-link:hover {
        color: #395750;
        border-bottom-color: #7A9F95;
    }

    .att-report .att-tabs .nav-link.active {
        color: #395750;
        background: transparent;
        border-bottom: 2px solid #395750;
    }

    .att-report .att-tabs .nav-link .badge {
        background-color: #e5edea;
        color: #395750;
        font-weight: 600;
    }

    .att-report .att-tabs .nav-link.active .badge {
        background-color: #395750;
        color: #fff;
    }

    /* Numbering column */
    .att-num {
        width: 54px;
        text-align: center;
        color: #98a2a0;
        font-variant-numeric: tabular-nums;
        font-size: 12px;
    }

    /* Data-check blocks */
    .att-check+.att-check {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid #eef1f0;
    }

    .att-check-title {
        font-weight: 700;
        color: #395750;
        margin-bottom: 4px;
    }
</style>

@include('attendance.reports.partials.report-style')
@endsection
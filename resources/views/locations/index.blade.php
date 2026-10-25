@extends('layouts.master')

@section('content')
@php
$coverage = $stats->branches_total > 0
? round($stats->branches_mapped / $stats->branches_total * 100, 1)
: null;
@endphp

<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Attendance Locations</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Attendance Locations</li>
                    </ul>
                </div>
                <div class="col-auto d-flex flex-wrap gap-2">
                    <a href="{{ route('attendance.locations.assign') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-users"></i> Assign staff
                    </a>
                    <a href="{{ route('attendance.locations.create') }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-plus"></i> Add location
                    </a>
                </div>
            </div>
        </div>

        {{-- KPI tiles: one row, straight under the title. --}}
        <div class="row row-cols-2 row-cols-sm-3 row-cols-lg-6 g-3 mb-3">
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-map-marker-alt"></i> Sites</span>
                    <span class="att-tile-value">{{ $stats->total }}</span>
                    <span class="att-tile-sub">{{ $stats->active }} active</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-link"></i> In use</span>
                    <span class="att-tile-value">{{ $stats->used }}</span>
                    <span class="att-tile-sub">linked to people or branches</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile{{ $stats->unused > 0 ? ' att-tile-alert' : '' }}">
                    <span class="att-tile-label"><i class="fa fa-unlink"></i> Unused</span>
                    <span class="att-tile-value">{{ $stats->unused }}</span>
                    <span class="att-tile-sub">no branches or people</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-user-check"></i> People on site</span>
                    <span class="att-tile-value">{{ number_format($stats->people_on_site) }}</span>
                    <span class="att-tile-sub">own working site</span>
                </div>
            </div>
            <div class="col">
                <div class="att-tile">
                    <span class="att-tile-label"><i class="fa fa-building"></i> Branch coverage</span>
                    <span class="att-tile-value">{{ $coverage === null ? '—' : number_format($coverage, 0) . '%' }}</span>
                    @if ($coverage === null)
                    <span class="att-tile-sub">no branches</span>
                    @else
                    <span class="rate-bar mt-1"><i data-rate="{{ $coverage }}"></i></span>
                    @endif
                </div>
            </div>
            <div class="col">
                <div class="att-tile{{ $branchesWithoutLocation > 0 ? ' att-tile-alert' : '' }}">
                    <span class="att-tile-label"><i class="fa fa-exclamation-triangle"></i> Unmapped</span>
                    <span class="att-tile-value">{{ $branchesWithoutLocation }}</span>
                    <span class="att-tile-sub">branches with no site</span>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                <form method="GET" action="{{ route('attendance.locations.index') }}" class="row g-2 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted mb-1" for="locQ">Search</label>
                        <input type="search" name="q" id="locQ" value="{{ $search }}"
                            class="form-control form-control-sm" placeholder="Site name or address">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1" for="locStatus">Status</label>
                        <select name="status" id="locStatus" class="form-select form-select-sm">
                            <option value="">Any status</option>
                            <option value="active" @selected($statusFilter==='active' )>Active only</option>
                            <option value="inactive" @selected($statusFilter==='inactive' )>Inactive only</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label small text-muted mb-1" for="locUsage">Usage</label>
                        <select name="usage" id="locUsage" class="form-select form-select-sm">
                            <option value="">Any usage</option>
                            <option value="used" @selected($usage==='used' )>Used by someone</option>
                            <option value="unused" @selected($usage==='unused' )>Not used at all</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-filter"></i> Apply</button>
                        <a href="{{ route('attendance.locations.index') }}" class="btn btn-sm btn-light">Reset</a>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-3 small text-muted mt-3 pt-2 border-top">
                    <span><i class="fa fa-info-circle"></i> Resolution: employee override &rarr; branch location. No
                        site means the punch is recorded as on duty, with a reason.</span>
                    <span><i class="fa fa-crosshairs"></i> Punches outside the radius are rejected with the measured
                        distance</span>
                </div>
            </div>
        </div>

        {{-- Locations --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header att-card-header py-3">
                <strong>Geofences</strong>
                <span class="badge ms-1">{{ $locations->count() }}</span>
                <div class="small text-muted">Each site is a circle: the centre plus the allowed radius.</div>
            </div>

            @if ($stats->total === 0)
            <div class="card-body">
                <div class="att-empty">
                    <i class="fa fa-map-marker-alt"></i>
                    <h6>No attendance sites yet</h6>
                    <p>Add the first office so employees can punch in.</p>
                    <a href="{{ route('attendance.locations.create') }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-plus"></i> Add location
                    </a>
                </div>
            </div>
            @elseif ($locations->isEmpty())
            <div class="card-body">
                <div class="att-empty">
                    <i class="fa fa-search"></i>
                    <h6>No site matches these filters</h6>
                    <p>Try another name, or clear the status and usage filters.</p>
                    <a href="{{ route('attendance.locations.index') }}" class="btn btn-sm btn-brand">Reset filters</a>
                </div>
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 att-table">
                    <thead>
                        <tr>
                            <th class="att-num">#</th>
                            <th>Location</th>
                            <th>Coordinates</th>
                            <th class="text-center">Radius</th>
                            <th class="text-center">Branches</th>
                            <th class="text-center">Employees</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($locations as $location)
                        <tr class="{{ $location->is_active ? '' : 'row-none' }}">
                            <td class="att-num">{{ $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold">
                                    {{ $location->name }}
                                </div>
                                @if ($location->address)
                                <div class="text-muted small">{{ $location->address }}</div>
                                @endif
                            </td>
                            <td class="text-muted small text-nowrap">
                                <i class="fa fa-map-pin me-1"></i>{{ number_format((float) $location->latitude, 6) }},
                                {{ number_format((float) $location->longitude, 6) }}
                            </td>
                            <td class="text-center text-nowrap">{{ $location->radius_m }} m</td>
                            <td class="text-center">
                                @if ($location->branches_count > 0)
                                {{ $location->branches_count }}
                                @else
                                <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($location->users_count > 0)
                                {{ $location->users_count }}
                                @else
                                <span class="text-muted">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if ($location->is_active)
                                <span class="chip chip-good">Active</span>
                                @else
                                <span class="chip chip-muted">Inactive</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('attendance.locations.edit', $location->id) }}"
                                        class="btn btn-sm btn-outline-secondary">
                                        <i class="fa fa-pen"></i> Edit
                                    </a>

                                    <form action="{{ route('attendance.locations.destroy', $location->id) }}"
                                        method="POST" onsubmit="return confirm('Delete {{ $location->name }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fa fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white small text-muted">
                Showing {{ $locations->count() }} of {{ $stats->total }} site(s)
            </div>
            @endif
        </div>

        {{-- ============ DATA CHECKS (collapsed, bottom of the page) ============ --}}
        @if ($branchesWithoutLocation > 0 || $stats->unused > 0)
        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header att-card-header py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <strong>Data checks</strong>
                    <span class="badge ms-1">{{ ($branchesWithoutLocation > 0 ? 1 : 0) + ($stats->unused > 0 ? 1 : 0) }}</span>
                    <div class="small text-muted">Setups that can send a punch to the wrong site.</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse"
                    data-bs-target="#checksPanel" aria-expanded="false" aria-controls="checksPanel">
                    <i class="fa fa-chevron-down"></i> Show details
                </button>
            </div>
            <div class="collapse" id="checksPanel">
                <div class="card-body">
                    @if ($branchesWithoutLocation > 0)
                    <div class="att-check">
                        <div class="att-check-title">
                            <i class="fa fa-exclamation-triangle text-warning"></i>
                            {{ $branchesWithoutLocation }} of {{ $branchesTotal }} branch(es) have no site
                        </div>
                        <p class="mb-2">
                            Their employees have no working location, so their punches are recorded as on duty and
                            ask for a reason. Map them on the
                            <a href="{{ route('attendance.locations.assign', ['tab' => 'branches', 'mapped' => 'unmapped']) }}">Branch
                                sites tab</a>.
                        </p>
                    </div>
                    @endif

                    @if ($stats->unused > 0)
                    <div class="att-check">
                        <div class="att-check-title">
                            <i class="fa fa-unlink text-warning"></i>
                            {{ $stats->unused }} site(s) are not used by anyone
                        </div>
                        <p class="mb-0">
                            No branch and no employee points at these sites. They are safe to delete, or keep them for
                            a future office.
                            <a href="{{ route('attendance.locations.index', ['usage' => 'unused']) }}">Review them</a>.
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
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
    })();
</script>

@include('attendance.reports.partials.report-style')
@endsection
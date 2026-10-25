@extends('layouts.master')

@section('title', 'Dashboard')

@section('content')
@php
$user = auth()->user();
$isManager = $user->hasAnyRole(['Admin', 'Super Admin', 'HR', 'branch_manager', 'Pas Director']);
$today = \Carbon\Carbon::today();
$todayAttendance = \App\Models\Attendance::where('user_id', $user->id)
->whereDate('date', $today)->first();
$monthCount = \App\Models\Attendance::where('user_id', $user->id)
->whereYear('date', $today->year)->whereMonth('date', $today->month)->count();
@endphp

<div class="page-wrapper">
    <div class="content container-fluid">

        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Welcome back, {{ $user->name }} 👋</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Overview</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <span class="text-muted small">{{ $today->format('l, j F Y') }}</span>
                </div>
            </div>
        </div>

        {{-- Quick stats --}}
        <div class="row g-4">
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Today</div>
                        <div class="fs-5 fw-semibold text-brand">
                            @if ($todayAttendance && $todayAttendance->clock_out)
                            Completed
                            @elseif ($todayAttendance)
                            Working
                            @else
                            Not started
                            @endif
                        </div>
                        <div class="small text-muted">
                            {{ $todayAttendance?->clock_in ? 'In ' . \Carbon\Carbon::parse($todayAttendance->clock_in)->format('H:i') : 'No check-in' }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Days logged this month</div>
                        <div class="fs-5 fw-semibold text-brand">{{ $monthCount }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Branch</div>
                        <div class="fs-5 fw-semibold text-brand">{{ $user->branch?->name ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted small">Role</div>
                        <div class="fs-5 fw-semibold text-brand">{{ $user->roles->pluck('name')->first() ?? 'Employee' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="row g-4 mt-1">
            <div class="col-md-6 col-lg-3">
                <a href="{{ route('attendance.page') }}" class="card text-decoration-none h-100">
                    <div class="card-body">
                        <i class="fa fa-clock-o fa-2x text-brand"></i>
                        <h6 class="mt-2 mb-1">My Attendance</h6>
                        <p class="text-muted small mb-0">Timesheet &amp; punch history</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="{{ route('attendance.index') }}" class="card text-decoration-none h-100">
                    <div class="card-body">
                        <i class="fa fa-qrcode fa-2x text-brand"></i>
                        <h6 class="mt-2 mb-1">QR Check-in</h6>
                        <p class="text-muted small mb-0">Scan to check in / out</p>
                    </div>
                </a>
            </div>

            @if ($isManager)
            <div class="col-md-6 col-lg-3">
                <a href="{{ route('attendance.reports.branch') }}" class="card text-decoration-none h-100">
                    <div class="card-body">
                        <i class="fa fa-building-o fa-2x text-brand"></i>
                        <h6 class="mt-2 mb-1">Branch Reports</h6>
                        <p class="text-muted small mb-0">Attendance by branch</p>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-lg-3">
                <a href="{{ route('attendance.locations.index') }}" class="card text-decoration-none h-100">
                    <div class="card-body">
                        <i class="fa fa-map-marker fa-2x text-brand"></i>
                        <h6 class="mt-2 mb-1">Locations</h6>
                        <p class="text-muted small mb-0">Manage geofences</p>
                    </div>
                </a>
            </div>
            @endif
        </div>

    </div>
</div>
@endsection
@extends('layouts.master')

@section('title', 'My Leave')

@section('content')
<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">My Leave</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">My Leave</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('leaves.create') }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-plus"></i> Request leave
                    </a>
                </div>
            </div>
        </div>

        {{-- Balance --}}
        <div class="row g-3 mb-3">
            @foreach ($balances as $b)
            <div class="col-6 col-lg-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $b['type'] }} leave</div>
                        <div class="fs-4 fw-semibold">
                            {{ $b['balance'] === null ? '—' : $b['balance'] }}
                            <span class="fs-6 text-muted">left</span>
                        </div>
                        <div class="small text-muted">
                            of {{ $b['quota'] === null ? '∞' : rtrim(rtrim(number_format($b['quota'], 2, '.', ''), '0'), '.') }}
                            &middot; used {{ $b['used'] }}
                            @if ($b['pending'] > 0)&middot; pending {{ $b['pending'] }}@endif
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- Year filter --}}
        <form method="GET" action="{{ route('leaves.index') }}" class="mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-auto"><label class="form-label mb-0 small text-muted">Year</label></div>
                <div class="col-sm-3 col-lg-2">
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        @foreach ($years as $y)
                        <option value="{{ $y }}" @selected((int) $year===(int) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>

        {{-- Requests --}}
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Days</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leaves as $leave)
                        <tr>
                            <td class="fw-semibold">{{ $leave->leave_type ?: '—' }}</td>
                            <td>
                                {{ optional($leave->from_date)->format('d M Y') ?: '—' }}
                                @if ($leave->to_date && optional($leave->to_date)->ne($leave->from_date))
                                &rarr; {{ optional($leave->to_date)->format('d M Y') }}
                                @endif
                            </td>
                            <td>{{ $leave->workingDays() }}</td>
                            <td>
                                @php
                                $badge = match ($leave->status) {
                                \App\Models\Leave::STATUS_APPROVED => 'bg-success',
                                \App\Models\Leave::STATUS_REJECTED => 'bg-danger',
                                default => 'bg-warning text-dark',
                                };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($leave->status) }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('leaves.show', $leave) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-eye"></i> View
                                </a>
                                @if ($leave->status === \App\Models\Leave::STATUS_PENDING)
                                <form method="POST" action="{{ route('leaves.cancel', $leave) }}" class="d-inline"
                                    onsubmit="return confirm('Cancel this request?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-times"></i> Cancel
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No leave requests yet.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $leaves->links() }}
        </div>
    </div>
</div>
@endsection
@extends('layouts.master')

@section('title', 'Leave Approvals')

@section('content')
<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Leave Management</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Leaves</li>
                    </ul>
                </div>
            </div>
        </div>

        {{-- Status tabs + search --}}
        <form method="GET" action="{{ route('leave-approvals.index') }}" class="mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <div class="btn-group" role="group">
                        @php
                        $tabs = [
                        \App\Models\Leave::STATUS_PENDING => 'Pending',
                        \App\Models\Leave::STATUS_APPROVED => 'Approved',
                        \App\Models\Leave::STATUS_REJECTED => 'Rejected',
                        '' => 'All',
                        ];
                        @endphp
                        @foreach ($tabs as $val => $label)
                        <a href="{{ route('leave-approvals.index', array_filter(['status' => $val])) }}"
                            class="btn btn-sm {{ $status === $val ? 'btn-brand' : 'btn-outline-secondary' }}">
                            {{ $label }}
                            @if ($val !== '' && isset($counts[$val]))
                            <span class="badge bg-light text-dark ms-1">{{ $counts[$val] }}</span>
                            @endif
                        </a>
                        @endforeach
                    </div>
                </div>
                <div class="col-sm-4 col-lg-3 ms-auto d-flex gap-2">
                    <input type="text" name="search" value="{{ request('search') }}"
                        class="form-control form-control-sm" placeholder="Search employee">
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-search"></i></button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Employee</th>
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
                            <td class="fw-semibold">{{ $leave->user?->name ?: '—' }}</td>
                            <td>{{ $leave->leave_type ?: '—' }}</td>
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
                                <a href="{{ route('leave-approvals.show', $leave) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-eye"></i> Review
                                </a>
                                @if ($leave->status === \App\Models\Leave::STATUS_PENDING)
                                <form method="POST" action="{{ route('leave-approvals.approve', $leave) }}" class="d-inline">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-brand">
                                        <i class="fa fa-check"></i> Approve
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No leave requests found.</td>
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
@extends('layouts.master')

@section('title', 'Leave Request')

@section('content')
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Leave Request #{{ $leave->id }}</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('leaves.index') }}">My Leave</a></li>
                        <li class="breadcrumb-item active">#{{ $leave->id }}</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('leaves.index') }}" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-muted">Status</dt>
                    <dd class="col-sm-9">
                        @php
                        $badge = match ($leave->status) {
                        \App\Models\Leave::STATUS_APPROVED => 'bg-success',
                        \App\Models\Leave::STATUS_REJECTED => 'bg-danger',
                        default => 'bg-warning text-dark',
                        };
                        @endphp
                        <span class="badge {{ $badge }}">{{ ucfirst($leave->status) }}</span>
                    </dd>

                    <dt class="col-sm-3 text-muted">Type</dt>
                    <dd class="col-sm-9">{{ $leave->leave_type ?: '—' }}</dd>

                    <dt class="col-sm-3 text-muted">From</dt>
                    <dd class="col-sm-9">{{ optional($leave->from_date)->format('D, d M Y') ?: '—' }}</dd>

                    <dt class="col-sm-3 text-muted">To</dt>
                    <dd class="col-sm-9">{{ optional($leave->to_date)->format('D, d M Y') ?: '—' }}</dd>

                    <dt class="col-sm-3 text-muted">Days (working)</dt>
                    <dd class="col-sm-9">{{ $days }}</dd>

                    <dt class="col-sm-3 text-muted">Reason</dt>
                    <dd class="col-sm-9">{{ $leave->reason ?: '—' }}</dd>

                    @if ($leave->isApproved() && $leave->approver)
                    <dt class="col-sm-3 text-muted">Approved by</dt>
                    <dd class="col-sm-9">{{ $leave->approver->name }} &middot; {{ optional($leave->approved_at)->format('d M Y H:i') }}</dd>
                    @endif

                    @if ($leave->status === \App\Models\Leave::STATUS_REJECTED && $leave->approver)
                    <dt class="col-sm-3 text-muted">Decision by</dt>
                    <dd class="col-sm-9">{{ $leave->approver->name }} &middot; {{ optional($leave->approved_at)->format('d M Y H:i') }}</dd>

                    <dt class="col-sm-3 text-muted">Rejection reason</dt>
                    <dd class="col-sm-9 text-danger">{{ $leave->rejection_reason ?: '—' }}</dd>
                    @endif
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
@extends('layouts.master')

@section('title', 'Review Leave Request')

@section('content')
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Leave Request #{{ $leave->id }}</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('leave-approvals.index') }}">Leave Management</a></li>
                        <li class="breadcrumb-item active">#{{ $leave->id }}</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('leave-approvals.index') }}" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted">Employee</dt>
                            <dd class="col-sm-8">{{ $leave->user?->name ?: '—' }}</dd>

                            <dt class="col-sm-4 text-muted">Type</dt>
                            <dd class="col-sm-8">{{ $leave->leave_type ?: '—' }}</dd>

                            <dt class="col-sm-4 text-muted">From</dt>
                            <dd class="col-sm-8">{{ optional($leave->from_date)->format('D, d M Y') ?: '—' }}</dd>

                            <dt class="col-sm-4 text-muted">To</dt>
                            <dd class="col-sm-8">{{ optional($leave->to_date)->format('D, d M Y') ?: '—' }}</dd>

                            <dt class="col-sm-4 text-muted">Days (working)</dt>
                            <dd class="col-sm-8">{{ $days }}</dd>

                            <dt class="col-sm-4 text-muted">Reason</dt>
                            <dd class="col-sm-8">{{ $leave->reason ?: '—' }}</dd>

                            <dt class="col-sm-4 text-muted">Status</dt>
                            <dd class="col-sm-8">
                                @php
                                $badge = match ($leave->status) {
                                \App\Models\Leave::STATUS_APPROVED => 'bg-success',
                                \App\Models\Leave::STATUS_REJECTED => 'bg-danger',
                                default => 'bg-warning text-dark',
                                };
                                @endphp
                                <span class="badge {{ $badge }}">{{ ucfirst($leave->status) }}</span>
                            </dd>

                            @if ($leave->status !== \App\Models\Leave::STATUS_PENDING && $leave->approver)
                            <dt class="col-sm-4 text-muted">Decision by</dt>
                            <dd class="col-sm-8">{{ $leave->approver->name }} &middot; {{ optional($leave->approved_at)->format('d M Y H:i') }}</dd>
                            @endif

                            @if ($leave->rejection_reason)
                            <dt class="col-sm-4 text-muted">Rejection reason</dt>
                            <dd class="col-sm-8 text-danger">{{ $leave->rejection_reason }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>

            @if ($leave->status === \App\Models\Leave::STATUS_PENDING)
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header bg-white fw-semibold">Decision</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('leave-approvals.approve', $leave) }}" class="mb-3">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-brand w-100">
                                <i class="fa fa-check"></i> Approve leave
                            </button>
                        </form>

                        <form method="POST" action="{{ route('leave-approvals.reject', $leave) }}">
                            @csrf @method('PATCH')
                            <div class="mb-2">
                                <label class="form-label">Rejection reason <span class="text-danger">*</span></label>
                                <textarea name="rejection_reason" rows="3"
                                    class="form-control @error('rejection_reason') is-invalid @enderror"
                                    placeholder="Why is this being rejected?">{{ old('rejection_reason') }}</textarea>
                                @error('rejection_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="fa fa-times"></i> Reject leave
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
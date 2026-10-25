@extends('layouts.master')

@section('title', 'Request Leave')

@section('content')
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Request Leave</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('leaves.index') }}">My Leave</a></li>
                        <li class="breadcrumb-item active">Request</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('leaves.index') }}" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back to my leave
                    </a>
                </div>
            </div>
        </div>

        @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('leaves.store') }}">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Leave type <span class="text-danger">*</span></label>
                            <select name="leave_type" class="form-select @error('leave_type') is-invalid @enderror" required>
                                @foreach ($types as $type)
                                <option value="{{ $type }}" @selected(old('leave_type', 'Annual' )===$type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('leave_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">From <span class="text-danger">*</span></label>
                            <input type="date" name="from_date" value="{{ old('from_date') }}"
                                class="form-control @error('from_date') is-invalid @enderror" required>
                            @error('from_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">To <span class="text-danger">*</span></label>
                            <input type="date" name="to_date" value="{{ old('to_date') }}"
                                class="form-control @error('to_date') is-invalid @enderror" required>
                            @error('to_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="1" id="is_half_day" name="is_half_day"
                                    @checked(old('is_half_day'))>
                                <label class="form-check-label" for="is_half_day">Half day (uses the "From" date only)</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Half-day part</label>
                            <select name="half_day_type" class="form-select">
                                <option value="morning" @selected(old('half_day_type')==='morning' )>Morning</option>
                                <option value="afternoon" @selected(old('half_day_type')==='afternoon' )>Afternoon</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" rows="3"
                                class="form-control @error('reason') is-invalid @enderror"
                                placeholder="Briefly describe the reason">{{ old('reason') }}</textarea>
                            @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-brand">
                            <i class="fa fa-paper-plane"></i> Submit request
                        </button>
                        <a href="{{ route('leaves.index') }}" class="btn btn-light">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header bg-white fw-semibold"><i class="fa fa-balance-scale"></i> Current-year balance</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Quota</th>
                            <th>Used</th>
                            <th>Pending</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($balances as $b)
                        <tr>
                            <td>{{ $b['type'] }}</td>
                            <td>{{ $b['quota'] === null ? '—' : rtrim(rtrim(number_format($b['quota'], 2, '.', ''), '0'), '.') }}</td>
                            <td>{{ $b['used'] }}</td>
                            <td>{{ $b['pending'] }}</td>
                            <td class="text-end fw-semibold">{{ $b['balance'] === null ? '—' : $b['balance'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
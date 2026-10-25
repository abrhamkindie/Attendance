@extends('layouts.master')

@section('title', 'Holiday Management')

@section('content')
<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Holidays</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Holidays</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('holidays.create') }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-plus"></i> Add holiday
                    </a>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('holidays.index') }}" class="mb-3">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <label class="form-label mb-0 small text-muted">Year</label>
                </div>
                <div class="col-sm-3 col-lg-2">
                    <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">All years</option>
                        @foreach ($years as $y)
                        <option value="{{ $y }}" @selected((int) request('year')===(int) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <a href="{{ route('holidays.index') }}" class="btn btn-sm btn-light">Reset</a>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Holiday</th>
                            <th>Year</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($holidays as $holiday)
                        <tr>
                            <td class="fw-semibold">
                                {{ $holiday->holidayDate()?->format('D, d M Y') ?: ($holiday->date_holiday ?: '—') }}
                            </td>
                            <td>{{ $holiday->name ?: '—' }}</td>
                            <td class="text-muted">{{ $holiday->year ?: ($holiday->holidayDate()?->year ?? '—') }}</td>
                            <td class="text-end">
                                <a href="{{ route('holidays.edit', $holiday) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-pencil"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" class="d-inline"
                                    onsubmit="return confirm('Delete this holiday?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center text-muted py-4">No holidays found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
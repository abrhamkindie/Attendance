@extends('layouts.master')

@section('title', 'Holidays')

@section('content')
<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Company Holidays</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Holidays</li>
                    </ul>
                </div>
            </div>
        </div>

        @if (count($grouped) === 0)
        <div class="card">
            <div class="card-body text-center text-muted py-5">No holidays have been published yet.</div>
        </div>
        @else
        @foreach ($grouped as $year => $holidays)
        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="fa fa-calendar"></i> {{ $year }}
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Holiday</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($holidays as $holiday)
                        @php $d = $holiday->holidayDate(); @endphp
                        <tr>
                            <td class="fw-semibold">{{ $d ? $d->format('D, d M Y') : ($holiday->date_holiday ?: '—') }}</td>
                            <td>{{ $holiday->name ?: '—' }}</td>
                            <td class="text-end">
                                @if ($d && $d->isToday())
                                <span class="badge bg-brand">Today</span>
                                @elseif ($d && $d->lt($today))
                                <span class="badge bg-light text-dark">Past</span>
                                @else
                                <span class="badge bg-success-subtle text-success-emphasis border border-success">Upcoming</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endforeach
        @endif
    </div>
</div>
@endsection
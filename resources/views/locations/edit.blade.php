@extends('layouts.master')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    // If the primary CDN is blocked, fall back to jsDelivr instead of leaving a blank map.
    if (typeof L === 'undefined') {
        document.write('<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css">');
        document.write('<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js"><\/script>');
    }
</script>

<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Edit Attendance Location</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('attendance.locations.index') }}">Attendance Locations</a></li>
                        <li class="breadcrumb-item active">{{ $location->name }}</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('attendance.locations.index') }}" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back to list
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

        @include('locations.partials.form', [
        'location' => $location,
        'action' => route('attendance.locations.update', $location->id),
        'method' => 'PUT',
        ])
    </div>
</div>
@endsection
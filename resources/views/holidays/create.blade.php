@extends('layouts.master')

@section('title', 'Add Holiday')

@section('content')
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Add Holiday</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('holidays.index') }}">Holidays</a></li>
                        <li class="breadcrumb-item active">Add</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('holidays.index') }}" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back to list
                    </a>
                </div>
            </div>
        </div>

        @include('holidays.partials.form', [
        'holiday' => null,
        'action' => route('holidays.store'),
        'method' => 'POST',
        ])
    </div>
</div>
@endsection
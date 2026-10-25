@extends('layouts.master')

@section('title', 'Edit Branch')

@section('content')
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Edit Branch — {{ $branch->name }}</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('branches.index') }}">Branches</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('branches.index') }}" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back to list
                    </a>
                </div>
            </div>
        </div>

        @include('branches.partials.form', [
        'action' => route('branches.update', $branch),
        'method' => 'PUT',
        ])
    </div>
</div>
@endsection
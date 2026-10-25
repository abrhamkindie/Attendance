@extends('layouts.master')

@section('title', 'Add User')

@section('content')
<div class="page-wrapper">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Add User</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                        <li class="breadcrumb-item active">Add</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('users.index') }}" class="btn btn-light">
                        <i class="fa fa-arrow-left"></i> Back to list
                    </a>
                </div>
            </div>
        </div>

        @include('users.partials.form', [
        'user' => null,
        'action' => route('users.store'),
        'method' => 'POST',
        ])
    </div>
</div>
@endsection
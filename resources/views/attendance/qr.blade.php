@extends('layouts.master')

@section('content')

    <!-- Page Wrapper -->
    <div class="page-wrapper">
        <!-- Page Content -->
        <div class="content container-fluid">
            <!-- Page Header -->
            <div class="page-header">
                <div class="row">
                    <div class="col-sm-12">
                        <h3 class="page-title">QR Check-In</h3>
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                            <li class="breadcrumb-item active">QR Check-In</li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- /Page Header -->

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body text-center py-5">

                            <div class="mb-4">
                                <i class="fa fa-qrcode" style="font-size: 64px; color: #395750;"></i>
                            </div>

                            <h4 class="mb-2" style="color: #395750;">
    Scan this QR code to 
    @if(($type ?? 'in') === 'out')
        clock out
    @else
        clock in
    @endif
</h4>
                            <p class="text-muted mb-4">
                                This code expires in 30 minutes.
                            </p>

                            <div class="my-4 d-flex justify-content-center">
                                {!! DNS2D::getBarcodeHTML($url, 'QRCODE', 8, 8) !!}
                            </div>

                            <p class="text-muted mb-0">
                                <small>
                                    <i class="fa fa-mobile-alt"></i>
                                    Use your phone camera to scan.
                                </small>
                            </p>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Page Content -->
    </div>

@endsection
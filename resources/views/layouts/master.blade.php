<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title') — {{ config('app.name', 'Attendance') }}</title>

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    {{-- Bootstrap 5 + FontAwesome 4 (views use `fa fa-*`) + Toastr --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">

    <style>
        :root {
            --brand: #395750;
            --brand-dark: #22322e;
            --brand-darker: #101a17;
            --brand-50: #f3f6f5;
            --brand-100: #e2eae7;
        }

        body {
            font-family: "Figtree", system-ui, -apple-system, sans-serif;
            background: #f5f7f6;
            color: #1f2a28;
        }

        a {
            text-decoration: none;
        }

        /* Brand helpers used across the feature views */
        .btn-brand {
            background-color: var(--brand);
            border-color: var(--brand);
            color: #fff;
            font-weight: 600;
        }

        .btn-brand:hover,
        .btn-brand:focus {
            background-color: var(--brand-dark);
            border-color: var(--brand-dark);
            color: #fff;
        }

        .text-brand {
            color: var(--brand) !important;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: var(--brand-dark);
            margin: 0;
        }

        .card {
            border: 1px solid #e6ebe9;
            border-radius: .75rem;
            box-shadow: 0 1px 2px rgba(16, 26, 23, .06);
        }

        /* ---- App shell ---- */
        .app-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 260px;
            background: linear-gradient(180deg, var(--brand) 0%, var(--brand-dark) 100%);
            color: #cfe0da;
            display: flex;
            flex-direction: column;
            z-index: 1040;
            transition: transform .25s ease;
        }

        .app-brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: 1.1rem 1.25rem;
            font-weight: 700;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .app-brand svg {
            width: 30px;
            height: 30px;
            flex: 0 0 auto;
        }

        .app-nav {
            padding: .75rem .6rem;
            overflow-y: auto;
            flex: 1 1 auto;
        }

        .app-nav .nav-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #8fb0a6;
            padding: .9rem .8rem .35rem;
        }

        .app-nav a {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem .8rem;
            border-radius: .5rem;
            color: #dbe8e3;
            font-size: .925rem;
            margin-bottom: .12rem;
        }

        .app-nav a i {
            width: 18px;
            text-align: center;
            opacity: .9;
        }

        .app-nav a:hover {
            background: rgba(255, 255, 255, .08);
            color: #fff;
        }

        .app-nav a.active {
            background: rgba(255, 255, 255, .16);
            color: #fff;
            font-weight: 600;
        }

        .app-main {
            margin-left: 260px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-topbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            height: 60px;
            background: #fff;
            border-bottom: 1px solid #e6ebe9;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.25rem;
        }

        .app-topbar .avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--brand-100);
            color: var(--brand-dark);
            display: grid;
            place-items: center;
            font-weight: 700;
        }

        .app-content {
            padding: 1.25rem;
            flex: 1 1 auto;
        }

        .sidebar-toggle {
            display: none;
            border: 1px solid #d6dedb;
            background: #fff;
            border-radius: .5rem;
            width: 40px;
            height: 40px;
        }

        @media (max-width: 991.98px) {
            .app-sidebar {
                transform: translateX(-100%);
            }

            .app-sidebar.show {
                transform: translateX(0);
            }

            .app-main {
                margin-left: 0;
            }

            .sidebar-toggle {
                display: inline-grid;
                place-items: center;
            }
        }

        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: .82rem;
        }

        .page-header {
            margin-bottom: 1.25rem;
        }
    </style>
</head>

@php
$user = auth()->user();
$isManager = $user && $user->hasAnyRole(['Admin', 'Super Admin', 'HR', 'branch_manager', 'Pas Director']);
$isHr = $user && $user->hasAnyRole(['Admin', 'Super Admin', 'HR']);
$isActive = fn ($patterns) => collect((array) $patterns)->contains(fn ($p) => request()->routeIs($p));
@endphp

<body>
    {{-- Sidebar --}}
    <aside class="app-sidebar" id="appSidebar">
        <div class="app-brand">
            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="4" width="18" height="17" rx="3" stroke="currentColor" stroke-width="1.8" />
                <path d="M3 9h18M8 2v4M16 2v4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                <path d="M8.5 14.5l2.5 2.5 4.5-5" stroke="#7fd3bf" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <span>Attendance</span>
        </div>

        <nav class="app-nav">
            <div class="nav-label">Main</div>
            <a href="{{ route('dashboard') }}" class="{{ $isActive('dashboard') ? 'active' : '' }}">
                <i class="fa fa-dashboard"></i> Dashboard
            </a>
            <a href="{{ route('attendance.index') }}" class="{{ $isActive('attendance.index', 'attendance.filter') ? 'active' : '' }}">
                <i class="fa fa-clock-o"></i> My Attendance
            </a>
            <a href="{{ route('attendance.page') }}" class="{{ $isActive('attendance.page') ? 'active' : '' }}">
                <i class="fa fa-table"></i> Attendance Sheet
            </a>
            <a href="{{ route('leaves.index') }}" class="{{ $isActive('leaves.*') ? 'active' : '' }}">
                <i class="fa fa-calendar-times-o"></i> My Leave
            </a>
            <a href="{{ route('holidays.browse') }}" class="{{ $isActive('holidays.browse') ? 'active' : '' }}">
                <i class="fa fa-birthday-cake"></i> Holidays
            </a>

            @if ($isManager)
            <div class="nav-label">Management</div>
            <a href="{{ route('attendance.reports.branch') }}" class="{{ $isActive('attendance.reports.branch') ? 'active' : '' }}">
                <i class="fa fa-building-o"></i> Branch Reports
            </a>
            <a href="{{ route('attendance.reports.department') }}" class="{{ $isActive('attendance.reports.department') ? 'active' : '' }}">
                <i class="fa fa-sitemap"></i> Department Reports
            </a>
            <a href="{{ route('attendance.locations.index') }}" class="{{ $isActive('attendance.locations.*') ? 'active' : '' }}">
                <i class="fa fa-map-marker"></i> Locations
            </a>
            @if ($isHr)
            <a href="{{ route('users.index') }}" class="{{ $isActive('users.*') ? 'active' : '' }}">
                <i class="fa fa-users"></i> Users
            </a>
            <a href="{{ route('departments.index') }}" class="{{ $isActive('departments.*') ? 'active' : '' }}">
                <i class="fa fa-sitemap"></i> Departments
            </a>
            <a href="{{ route('branches.index') }}" class="{{ $isActive('branches.*') ? 'active' : '' }}">
                <i class="fa fa-building"></i> Branches
            </a>
            <a href="{{ route('holidays.index') }}" class="{{ $isActive('holidays.index', 'holidays.create', 'holidays.edit') ? 'active' : '' }}">
                <i class="fa fa-birthday-cake"></i> Holidays
            </a>
            <a href="{{ route('leave-approvals.index') }}" class="{{ $isActive('leave-approvals.*') ? 'active' : '' }}">
                <i class="fa fa-check-square-o"></i> Leave Approvals
            </a>
            @endif
            @endif

            <div class="nav-label">Account</div>
            <a href="{{ route('profile.edit') }}" class="{{ $isActive('profile.*') ? 'active' : '' }}">
                <i class="fa fa-user"></i> Profile
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-100 text-start border-0 bg-transparent d-flex align-items-center gap-2 px-2 py-2 rounded" style="color:#dbe8e3">
                    <i class="fa fa-sign-out" style="width:18px"></i> Log Out
                </button>
            </form>
        </nav>
    </aside>

    {{-- Main --}}
    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-2">
                <button class="sidebar-toggle" type="button" onclick="document.getElementById('appSidebar').classList.toggle('show')">
                    <i class="fa fa-bars text-brand"></i>
                </button>
                <span class="fw-semibold text-brand">@yield('title', 'Dashboard')</span>
            </div>

            <div class="dropdown">
                <a href="#" class="d-flex align-items-center gap-2 text-dark" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="text-end d-none d-sm-block">
                        <span class="d-block fw-semibold" style="font-size:.9rem;line-height:1.1">{{ $user->name }}</span>
                        <span class="d-block text-muted" style="font-size:.75rem">{{ $user->roles->pluck('name')->first() ?? 'Employee' }}</span>
                    </span>
                    <span class="avatar">{{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="fa fa-user me-2"></i>Profile</a></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item"><i class="fa fa-sign-out me-2"></i>Log Out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <div class="app-content">
            {{-- Session flash (plain) --}}
            @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif
            @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif

            @yield('content')
        </div>
    </div>

    {{-- Vendor scripts --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    {{-- Toastr flash messages (brian2694/laravel-toastr + plain session) --}}
    @if (class_exists(\Toastr::class) || function_exists('toastr'))
    {!! toastr()->message() !!}
    @endif

    @stack('scripts')
    @yield('script')
</body>

</html>
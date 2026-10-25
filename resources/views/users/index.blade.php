@extends('layouts.master')

@section('title', 'User Management')

@section('content')
<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Users</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Users</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('users.create') }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-plus"></i> Add user
                    </a>
                </div>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('users.index') }}" class="mb-3">
            <div class="row g-2 align-items-end">
                <div class="col-sm-4 col-lg-3">
                    <input type="text" name="search" value="{{ request('search') }}"
                        class="form-control form-control-sm" placeholder="Search name or email">
                </div>
                <div class="col-sm-4 col-lg-2">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All roles</option>
                        @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected(request('role')===$role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <select name="branch_id" class="form-select form-select-sm">
                        <option value="">All branches</option>
                        @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) request('branch_id')===$branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <select name="department_id" class="form-select form-select-sm">
                        <option value="">All departments</option>
                        @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) request('department_id')===$department->id)>{{ $department->department }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-8 col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-filter"></i> Filter</button>
                    <a href="{{ route('users.index') }}" class="btn btn-sm btn-light">Reset</a>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $user->name }}</div>
                                @if ($user->is_representative || $user->is_department_representative)
                                <span class="badge bg-light text-dark">representative</span>
                                @endif
                            </td>
                            <td class="text-muted">{{ $user->email }}</td>
                            <td>
                                @forelse ($user->roles as $role)
                                <span class="badge bg-secondary">{{ $role->name }}</span>
                                @empty
                                <span class="text-muted">—</span>
                                @endforelse
                            </td>
                            <td>{{ $user->branch?->name ?? '—' }}</td>
                            <td>{{ $user->department?->department ?? '—' }}</td>
                            <td>
                                @php
                                $active = strtolower((string) $user->status) === 'active';
                                @endphp
                                <span class="badge {{ $active ? 'bg-success' : 'bg-danger' }}">{{ $user->status ?: 'Unknown' }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-pencil"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline"
                                    onsubmit="return confirm('Delete {{ $user->name }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No users found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
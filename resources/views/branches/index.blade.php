@extends('layouts.master')

@section('title', 'Branch Management')

@section('content')
<div class="page-wrapper att-report">
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h3 class="page-title">Branches</h3>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Branches</li>
                    </ul>
                </div>
                <div class="col-auto">
                    <a href="{{ route('branches.create') }}" class="btn btn-sm btn-brand">
                        <i class="fa fa-plus"></i> Add branch
                    </a>
                </div>
            </div>
        </div>

        <form method="GET" action="{{ route('branches.index') }}" class="mb-3">
            <div class="row g-2">
                <div class="col-sm-5 col-lg-4">
                    <input type="text" name="search" value="{{ request('search') }}"
                        class="form-control form-control-sm" placeholder="Search by name">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-brand"><i class="fa fa-search"></i> Search</button>
                    <a href="{{ route('branches.index') }}" class="btn btn-sm btn-light">Reset</a>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Location</th>
                            <th>Manager</th>
                            <th>Members</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($branches as $branch)
                        <tr>
                            <td class="fw-semibold">{{ $branch->name }}</td>
                            <td>{{ $branch->location?->name ?: '—' }}</td>
                            <td>{{ $branch->manager?->name ?: '—' }}</td>
                            <td>
                                <span class="badge bg-light text-dark">{{ $branch->users_count }}</span>
                            </td>
                            <td class="text-end">
                                <a href="{{ route('branches.edit', $branch) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-pencil"></i> Edit
                                </a>
                                <form method="POST" action="{{ route('branches.destroy', $branch) }}" class="d-inline"
                                    onsubmit="return confirm('Delete {{ $branch->name }}?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No branches found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-3">
            {{ $branches->links() }}
        </div>
    </div>
</div>
@endsection
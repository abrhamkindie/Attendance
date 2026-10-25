@php
$editing = isset($department) && $department;
@endphp

<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $action }}">
            @csrf
            @if ($method !== 'POST')
            @method($method)
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Department name <span class="text-danger">*</span></label>
                    <input type="text" name="department"
                        value="{{ old('department', $editing ? $department->department : '') }}"
                        class="form-control @error('department') is-invalid @enderror" required>
                    @error('department')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-brand">
                    <i class="fa fa-save"></i> {{ $editing ? 'Update department' : 'Create department' }}
                </button>
                <a href="{{ route('departments.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
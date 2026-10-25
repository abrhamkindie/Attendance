@php
$editing = isset($holiday) && $holiday;
$dateValue = $editing ? ($dateValue ?? $holiday->holidayDate()?->toDateString()) : '';
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
                    <label class="form-label">Holiday name <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                        value="{{ old('name', $editing ? $holiday->name : '') }}"
                        class="form-control @error('name') is-invalid @enderror"
                        placeholder="e.g. Jamhuri Day" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Date <span class="text-danger">*</span></label>
                    <input type="date" name="date"
                        value="{{ old('date', $dateValue) }}"
                        class="form-control @error('date') is-invalid @enderror" required>
                    @error('date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-brand">
                    <i class="fa fa-save"></i> {{ $editing ? 'Update holiday' : 'Create holiday' }}
                </button>
                <a href="{{ route('holidays.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
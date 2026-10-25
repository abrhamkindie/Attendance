@php
$editing = isset($branch) && $branch;
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
                    <label class="form-label">Branch name <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                        value="{{ old('name', $editing ? $branch->name : '') }}"
                        class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label class="form-label">Address</label>
                    <input type="text" name="address"
                        value="{{ old('address', $editing ? $branch->address : '') }}"
                        class="form-control @error('address') is-invalid @enderror">
                    @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Location (geofence)</label>
                    <select name="location_id" class="form-select @error('location_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach ($locations as $location)
                        <option value="{{ $location->id }}"
                            @selected((int) old('location_id', $editing ? $branch->location_id : 0) === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    @error('location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Manager</label>
                    <select name="manager_id" class="form-select @error('manager_id') is-invalid @enderror">
                        <option value="">— None —</option>
                        @foreach ($managers as $manager)
                        <option value="{{ $manager->id }}"
                            @selected((int) old('manager_id', $editing ? $branch->manager_id : 0) === $manager->id)>{{ $manager->name }}</option>
                        @endforeach
                    </select>
                    @error('manager_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-brand">
                    <i class="fa fa-save"></i> {{ $editing ? 'Update branch' : 'Create branch' }}
                </button>
                <a href="{{ route('branches.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
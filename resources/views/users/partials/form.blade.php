@php
$editing = isset($user) && $user;
$userId = $editing ? $user->id : null;
$roleName = $editing ? ($user->roles->first()->name ?? '') : old('role', 'Employee');

// join_date is a free-text column; normalise any legacy value to Y-m-d so the
// date picker can display it.
$joinDateValue = '';
if ($editing && $user->join_date) {
$parsedJoin = \App\Helpers\DateHelper::parseLooseDate($user->getRawOriginal('join_date'));
$joinDateValue = $parsedJoin ? $parsedJoin->format('Y-m-d') : '';
}
$joinDateValue = old('join_date', $joinDateValue);
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
                    <label class="form-label">Full name <span class="text-danger">*</span></label>
                    <input type="text" name="name"
                        value="{{ old('name', $editing ? $user->name : '') }}"
                        class="form-control @error('name') is-invalid @enderror" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email"
                        value="{{ old('email', $editing ? $user->email : '') }}"
                        class="form-control @error('email') is-invalid @enderror" required>
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-select @error('role') is-invalid @enderror">
                        @foreach ($roles as $role)
                        <option value="{{ $role->name }}" @selected($roleName===$role->name)>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        @php $status = old('status', $editing ? $user->status : 'Active'); @endphp
                        <option value="Active" @selected(strtolower((string) $status)==='active' )>Active</option>
                        <option value="Inactive" @selected(strtolower((string) $status)==='inactive' )>Inactive</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Join date</label>
                    <input type="date" name="join_date"
                        value="{{ $joinDateValue }}"
                        class="form-control @error('join_date') is-invalid @enderror">
                    @error('join_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) old('branch_id', $editing ? $user->branch_id : 0) === $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <select name="department_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((int) old('department_id', $editing ? $user->department_id : 0) === $department->id)>{{ $department->department }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Working location</label>
                    <select name="location_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach ($locations as $location)
                        <option value="{{ $location->id }}" @selected((int) old('location_id', $editing ? $user->location_id : 0) === $location->id)>{{ $location->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text">Geofence this person punches from by default.</div>
                </div>

                <div class="col-12">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="is_representative" value="1" id="is_representative"
                            @checked(old('is_representative', $editing ? $user->is_representative : false))>
                        <label class="form-check-label" for="is_representative">Branch representative</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="checkbox" name="is_department_representative" value="1" id="is_department_representative"
                            @checked(old('is_department_representative', $editing ? $user->is_department_representative : false))>
                        <label class="form-check-label" for="is_department_representative">Department representative</label>
                    </div>
                </div>
            </div>

            <div class="mt-4 d-flex gap-2">
                <button type="submit" class="btn btn-brand">
                    <i class="fa fa-save"></i> {{ $editing ? 'Update user' : 'Create user' }}
                </button>
                <a href="{{ route('users.index') }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </div>
</div>
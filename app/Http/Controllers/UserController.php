<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Brian2694\Toastr\Facades\Toastr;

class UserController extends Controller
{
    /**
     * Paginated, filterable list of everyone who can log in.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'branch', 'department', 'location']);

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        foreach (['branch_id' => 'branch_id', 'department_id' => 'department_id'] as $param => $column) {
            if ($value = $request->input($param)) {
                $query->where($column, $value);
            }
        }

        if ($role = $request->input('role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('users.index', [
            'users'       => $users,
            'branches'    => Branch::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('department')->get(['id', 'department']),
            'roles'       => Role::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('users.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'nullable|string|min:8',
            'status'   => 'nullable|string',
            'join_date' => 'nullable|date',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
            'role'      => 'nullable|string',
        ]);

        $user = User::create([
            'name'                          => $validated['name'],
            'email'                         => $validated['email'],
            'password'                      => $validated['password'] ?? 'password',
            'status'                        => $validated['status'] ?? 'Active',
            'join_date'                     => $validated['join_date'] ?? null,
            'branch_id'                     => $validated['branch_id'] ?? null,
            'department_id'                 => $validated['department_id'] ?? null,
            'location_id'                   => $validated['location_id'] ?? null,
            'is_representative'             => $request->boolean('is_representative'),
            'is_department_representative'  => $request->boolean('is_department_representative'),
        ]);

        if (! empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        } else {
            $user->syncRoles(['Employee']);
        }

        Toastr::success('User created successfully.', 'Success');

        return redirect()->route('users.index');
    }

    public function edit(User $user)
    {
        return view('users.edit', array_merge(
            $this->formOptions(),
            ['user' => $user]
        ));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'status'   => 'nullable|string',
            'join_date' => 'nullable|date',
            'branch_id' => 'nullable|integer',
            'department_id' => 'nullable|integer',
            'location_id' => 'nullable|integer',
            'role'      => 'nullable|string',
        ]);

        $data = [
            'name'                          => $validated['name'],
            'email'                         => $validated['email'],
            'status'                        => $validated['status'] ?? 'Active',
            'join_date'                     => $validated['join_date'] ?? null,
            'branch_id'                     => $validated['branch_id'] ?? null,
            'department_id'                 => $validated['department_id'] ?? null,
            'location_id'                   => $validated['location_id'] ?? null,
            'is_representative'             => $request->boolean('is_representative'),
            'is_department_representative'  => $request->boolean('is_department_representative'),
        ];

        // Only overwrite the password when one was supplied.
        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);
        $user->syncRoles([! empty($validated['role']) ? $validated['role'] : 'Employee']);

        Toastr::success('User updated successfully.', 'Success');

        return redirect()->route('users.index');
    }

    public function destroy(Request $request, User $user)
    {
        // Never let an account delete itself out of the system.
        if ($request->user() && $request->user()->id === $user->id) {
            Toastr::error('You cannot delete your own account.', 'Blocked');

            return back();
        }

        $user->delete();

        Toastr::success('User deleted successfully.', 'Success');

        return redirect()->route('users.index');
    }

    /**
     * Shared option lists for the create/edit forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'branches'    => Branch::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('department')->get(['id', 'department']),
            'locations'   => Location::orderBy('name')->get(['id', 'name']),
            'roles'       => Role::orderBy('name')->get(['id', 'name']),
        ];
    }
}

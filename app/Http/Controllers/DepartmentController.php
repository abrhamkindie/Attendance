<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Department::withCount('users');

        if ($search = trim((string) $request->input('search'))) {
            $query->where('department', 'like', "%{$search}%");
        }

        $departments = $query->orderBy('department')->paginate(15)->withQueryString();

        return view('departments.index', compact('departments'));
    }

    public function create()
    {
        return view('departments.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'department' => 'required|string|max:255|unique:departments,department',
        ]);

        Department::create($validated);

        Toastr::success('Department created successfully.', 'Success');

        return redirect()->route('departments.index');
    }

    public function edit(Department $department)
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'department' => ['required', 'string', 'max:255', Rule::unique('departments', 'department')->ignore($department->id)],
        ]);

        $department->update($validated);

        Toastr::success('Department updated successfully.', 'Success');

        return redirect()->route('departments.index');
    }

    public function destroy(Department $department)
    {
        $assigned = $department->users()->count();

        if ($assigned > 0) {
            Toastr::error(
                "Cannot delete: {$assigned} user(s) still belong to this department.",
                'Blocked'
            );

            return back();
        }

        $department->delete();

        Toastr::success('Department deleted successfully.', 'Success');

        return redirect()->route('departments.index');
    }
}

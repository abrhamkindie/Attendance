<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Location;
use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::with(['location', 'manager'])->withCount('users');

        if ($search = trim((string) $request->input('search'))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $branches = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('branches.index', compact('branches'));
    }

    public function create()
    {
        return view('branches.create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:branches,name',
            'address'     => 'nullable|string|max:255',
            'location_id' => 'nullable|integer|exists:locations,id',
            'manager_id'  => 'nullable|integer|exists:users,id',
        ]);

        Branch::create($validated);

        Toastr::success('Branch created successfully.', 'Success');

        return redirect()->route('branches.index');
    }

    public function edit(Branch $branch)
    {
        return view('branches.edit', array_merge(
            ['branch' => $branch],
            $this->formOptions()
        ));
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255', Rule::unique('branches', 'name')->ignore($branch->id)],
            'address'     => 'nullable|string|max:255',
            'location_id' => 'nullable|integer|exists:locations,id',
            'manager_id'  => 'nullable|integer|exists:users,id',
        ]);

        $branch->update($validated);

        Toastr::success('Branch updated successfully.', 'Success');

        return redirect()->route('branches.index');
    }

    public function destroy(Branch $branch)
    {
        $assigned = $branch->users()->count();

        if ($assigned > 0) {
            Toastr::error(
                "Cannot delete: {$assigned} user(s) still belong to this branch.",
                'Blocked'
            );

            return back();
        }

        $branch->delete();

        Toastr::success('Branch deleted successfully.', 'Success');

        return redirect()->route('branches.index');
    }

    /** Dropdown data shared by create / edit forms. */
    private function formOptions(): array
    {
        return [
            'locations' => Location::orderBy('name')->get(['id', 'name']),
            'managers'  => User::orderBy('name')->get(['id', 'name']),
        ];
    }
}

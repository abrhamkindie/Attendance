<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeLocationAssignment;
use App\Models\Location;
use App\Models\User;
use App\Services\Attendance\GoogleMapsLinkResolver;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use RuntimeException;

class LocationController extends Controller
{
    public function index(Request $request)
    {
        $search       = trim((string) $request->input('q'));
        $statusFilter = (string) $request->input('status', '');
        $usage        = (string) $request->input('usage', '');

        $all = Location::withCount(['users', 'branches'])
            ->orderBy('name')
            ->get();

        $locations = $all;

        if ($search !== '') {
            $locations = $locations->filter(function ($location) use ($search) {
                return mb_stripos((string) $location->name, $search) !== false
                    || mb_stripos((string) $location->address, $search) !== false;
            });
        }

        if ($statusFilter === 'active') {
            $locations = $locations->filter(fn($location) => (bool) $location->is_active);
        } elseif ($statusFilter === 'inactive') {
            $locations = $locations->filter(fn($location) => ! $location->is_active);
        }

        if ($usage === 'used') {
            $locations = $locations->filter(function ($location) {
                return $location->users_count > 0 || $location->branches_count > 0;
            });
        } elseif ($usage === 'unused') {
            $locations = $locations->filter(function ($location) {
                return $location->users_count === 0 && $location->branches_count === 0;
            });
        }

        $locations = $locations->values();

        $branchesWithoutLocation = Branch::whereNull('location_id')->count();
        $branchesTotal = Branch::count();

        $stats = (object) [
            'total'          => $all->count(),
            'active'         => $all->filter(fn($location) => (bool) $location->is_active)->count(),
            'used'           => $all->filter(function ($location) {
                return $location->users_count > 0 || $location->branches_count > 0;
            })->count(),
            'unused'         => $all->filter(function ($location) {
                return $location->users_count === 0 && $location->branches_count === 0;
            })->count(),
            'people_on_site' => (int) $all->sum('users_count'),
            'branches_total' => $branchesTotal,
            'branches_mapped' => $branchesTotal - $branchesWithoutLocation,
        ];

        return view('locations.index', compact(
            'locations',
            'stats',
            'search',
            'statusFilter',
            'usage',
            'branchesWithoutLocation',
            'branchesTotal'
        ));
    }

    public function create()
    {
        return view('locations.create');
    }

    public function store(Request $request)
    {
        $validated = $this->validatedData($request);

        Location::create($validated);

        Toastr::success('Location created successfully.', 'Success');

        return redirect()->route('attendance.locations.index');
    }

    public function edit(Location $location)
    {
        return view('locations.edit', compact('location'));
    }

    public function update(Request $request, Location $location)
    {
        $validated = $this->validatedData($request);

        $location->update($validated);

        Toastr::success('Location updated successfully.', 'Success');

        return redirect()->route('attendance.locations.index');
    }

    public function destroy(Location $location)
    {
        $users = $location->users()->count();
        $branches = $location->branches()->count();

        if ($users > 0 || $branches > 0) {
            Toastr::error(
                "This location is still used by {$users} employee(s) and {$branches} branch(es). Reassign them first.",
                'Cannot Delete'
            );

            return back();
        }

        $location->delete();

        Toastr::success('Location deleted successfully.', 'Success');

        return redirect()->route('attendance.locations.index');
    }

    /**
     * Branch -> location mapping, per-employee working location and dated
     * temporary postings, in three tabs on one screen.
     */
    public function assign(Request $request)
    {
        $tab = (string) $request->input('tab', 'branches');

        if (! in_array($tab, ['branches', 'employees', 'postings'], true)) {
            $tab = 'branches';
        }

        $search       = trim((string) $request->input('q'));
        $branchFilter = $request->input('branch_id');
        $status       = (string) $request->input('status', '');
        $postingState = (string) $request->input('posting', '');
        $cityFilter   = trim((string) $request->input('city'));
        $mapped       = (string) $request->input('mapped', '');
        $locationFilter = $request->input('location_id');

        $branches = Branch::with('location')
            ->withCount('users')
            ->orderBy('name')
            ->get();

        // The branches tab has its own filters. They are applied to a copy so
        // the dropdowns on the other two tabs keep the full list.
        $branchRows = $branches;

        if ($tab === 'branches' && $search !== '') {
            $branchRows = $branchRows->filter(function ($branch) use ($search) {
                return mb_stripos((string) $branch->name, $search) !== false
                    || mb_stripos((string) $branch->city, $search) !== false;
            });
        }

        if ($tab === 'branches' && $cityFilter !== '') {
            $branchRows = $branchRows->filter(function ($branch) use ($cityFilter) {
                return mb_strtolower(trim((string) $branch->city)) === mb_strtolower($cityFilter);
            });
        }

        if ($tab === 'branches' && $mapped === 'mapped') {
            $branchRows = $branchRows->filter(function ($branch) {
                return $branch->location_id !== null;
            });
        } elseif ($tab === 'branches' && $mapped === 'unmapped') {
            $branchRows = $branchRows->filter(function ($branch) {
                return $branch->location_id === null;
            });
        }

        $branchRows = $branchRows->values();

        $cities = $branches->pluck('city')
            ->map(fn($city) => trim((string) $city))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $locations = Location::withCount(['users', 'branches'])
            ->orderBy('name')
            ->get();

        $activeLocations = $locations->where('is_active', true)->values();

        $departments = Department::orderBy('department')->get(['id', 'department']);

        // ---------- Tab 2: employees (always listed, never search-only) ----------
        $employeeQuery = User::with(['location', 'branch.location', 'locationAssignments']);

        // The search box is shared between tabs, so only honour it on the tab
        // that is actually being displayed.
        $employeeSearch = $tab === 'employees' ? $search : '';

        if ($employeeSearch !== '') {
            $employeeQuery->where(function ($query) use ($employeeSearch) {
                $query->where('name', 'like', "%{$employeeSearch}%")
                    ->orWhere('email', 'like', "%{$employeeSearch}%");
            });
        }

        if ($branchFilter) {
            $employeeQuery->where('branch_id', $branchFilter);
        }

        switch ($status) {
            case 'posting':
                $employeeQuery->whereHas('locationAssignments', function ($query) {
                    $query->activeOn();
                });
                break;

            case 'override':
                $employeeQuery->whereNull('location_id')
                    ->whereDoesntHave('locationAssignments', function ($query) {
                        $query->activeOn();
                    })
                    ->whereHas('branch', function ($query) {
                        $query->whereNotNull('location_id');
                    });
                break;

            case 'fallback':
                $employeeQuery->whereNull('location_id')
                    ->whereDoesntHave('locationAssignments', function ($query) {
                        $query->activeOn();
                    })
                    ->whereDoesntHave('branch', function ($query) {
                        $query->whereNotNull('location_id');
                    });
                break;
        }

        $employees = $employeeQuery->orderBy('name')
            ->paginate(25, ['*'], 'employees_page')
            ->appends($request->query());

        // ---------- Tab 3: dated postings ----------
        $postingQuery = EmployeeLocationAssignment::with(['user.branch', 'location', 'author'])
            ->orderByDesc('starts_on')
            ->orderByDesc('id');

        $today = now()->toDateString();

        switch ($postingState) {
            case 'active':
                $postingQuery->activeOn();
                break;

            case 'scheduled':
                $postingQuery->whereDate('starts_on', '>', $today);
                break;

            case 'expired':
                $postingQuery->whereNotNull('ends_on')->whereDate('ends_on', '<', $today);
                break;
        }

        if ($tab === 'postings' && $search !== '') {
            $postingQuery->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            });
        }

        if ($tab === 'postings' && $locationFilter) {
            $postingQuery->where('location_id', $locationFilter);
        }

        $postings = $postingQuery
            ->paginate(25, ['*'], 'postings_page')
            ->appends($request->query());

        // ---------- Guard rails ----------
        $unmappedBranches = $branches->whereNull('location_id');

        $fallbackEmployees = User::whereNull('location_id')
            ->whereDoesntHave('locationAssignments', function ($query) {
                $query->activeOn();
            })
            ->whereDoesntHave('branch', function ($query) {
                $query->whereNotNull('location_id');
            })
            ->count();

        $duplicateSites   = $this->nearDuplicateSites($locations);
        $mixedCitySites   = $this->mixedCitySites($branches);

        $userOptions = User::orderBy('name')->get(['id', 'name']);

        // Headline numbers for the tiles at the top of the screen.
        $stats = (object) [
            'branches'        => $branches->count(),
            'unmapped'        => $unmappedBranches->count(),
            'employees'       => User::count(),
            'with_own_site'   => User::whereNotNull('location_id')->count(),
            'active_postings' => EmployeeLocationAssignment::activeOn()->count(),
            'sites'           => $activeLocations->count(),
            'total_sites'     => $locations->count(),
        ];

        $stats->mapped_rate = $stats->branches > 0
            ? round(($stats->branches - $stats->unmapped) / $stats->branches * 100, 1)
            : null;

        return view('locations.assign', compact(
            'branches',
            'branchRows',
            'cities',
            'locations',
            'activeLocations',
            'departments',
            'employees',
            'postings',
            'userOptions',
            'search',
            'branchFilter',
            'status',
            'postingState',
            'cityFilter',
            'mapped',
            'locationFilter',
            'unmappedBranches',
            'fallbackEmployees',
            'duplicateSites',
            'mixedCitySites',
            'stats',
            'tab'
        ));
    }

    public function assignBranches(Request $request)
    {
        $data = $request->validate([
            'branches'   => 'nullable|array',
            'branches.*' => [
                'nullable',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],
        ], [
            'branches.*.exists' => 'Only active locations can be assigned to a branch.',
        ]);

        $changed = 0;

        foreach ($data['branches'] ?? [] as $branchId => $locationId) {
            $branch = Branch::find($branchId);

            if (! $branch) {
                continue;
            }

            $newValue = $locationId ?: null;

            if ($branch->location_id !== $newValue) {
                $branch->update(['location_id' => $newValue]);
                $changed++;
            }
        }

        Toastr::success("Branch locations updated ({$changed} change(s)).", 'Success');

        return redirect()->route('attendance.locations.assign', ['tab' => 'branches']);
    }

    public function assignUser(Request $request)
    {
        $data = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'location_id' => [
                'nullable',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],
        ], [
            'location_id.exists' => 'That location is inactive. Activate it first or pick another one.',
        ]);

        User::whereKey($data['user_id'])->update([
            'location_id' => $data['location_id'] ?: null,
        ]);

        Toastr::success('Employee working location updated.', 'Success');

        return back();
    }

    /**
     * Bulk assignment: give every employee of a branch (or department) the
     * same working location in one action.
     */
    public function assignBulk(Request $request)
    {
        $data = $request->validate([
            'scope'       => 'required|in:branch,department,unassigned',
            'branch_id'   => 'required_if:scope,branch|nullable|exists:branches,id',
            'department_id' => 'required_if:scope,department|nullable|exists:departments,id',
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],
        ], [
            'location_id.exists' => 'That location is inactive. Activate it first or pick another one.',
        ]);

        $query = User::query();

        switch ($data['scope']) {
            case 'branch':
                $query->where('branch_id', $data['branch_id']);
                break;

            case 'department':
                $query->where('department_id', $data['department_id']);
                break;

            case 'unassigned':
                $query->whereNull('location_id')
                    ->whereDoesntHave('locationAssignments', function ($inner) {
                        $inner->activeOn();
                    })
                    ->whereDoesntHave('branch', function ($inner) {
                        $inner->whereNotNull('location_id');
                    });
                break;
        }

        $affected = $query->update(['location_id' => $data['location_id']]);

        $location = Location::find($data['location_id']);

        Toastr::success(
            "{$affected} employee(s) now work at {$location->name}.",
            'Bulk Assignment Saved'
        );

        return redirect()->route('attendance.locations.assign', ['tab' => 'employees']);
    }

    /**
     * Create a dated posting, e.g. "works at Head Office from 1 Nov to 15 Nov".
     * When ends_on passes the employee silently reverts to their normal site.
     */
    public function storePosting(Request $request)
    {
        $data = $request->validate([
            'user_id'     => 'required|exists:users,id',
            'location_id' => [
                'required',
                Rule::exists('locations', 'id')->where('is_active', true),
            ],
            'starts_on'   => 'required|date',
            'ends_on'     => 'nullable|date|after_or_equal:starts_on',
            'note'        => 'nullable|string|max:255',
        ], [
            'location_id.exists' => 'That location is inactive. Activate it first or pick another one.',
        ]);

        $data['created_by'] = Auth::id();

        EmployeeLocationAssignment::create($data);

        Toastr::success('Temporary posting saved. It applies from its start date.', 'Success');

        return redirect()->route('attendance.locations.assign', ['tab' => 'postings']);
    }

    public function destroyPosting(EmployeeLocationAssignment $posting)
    {
        $posting->delete();

        Toastr::success('Posting removed. The employee is back on their normal location.', 'Success');

        return redirect()->route('attendance.locations.assign', ['tab' => 'postings']);
    }

    /**
     * Two active sites are reported as duplicates when their names normalise to
     * the same string, when one name is a prefix of the other ("figa" /
     * "FIGA2"), or when they sit within 250 m of each other.
     *
     * @param  \Illuminate\Support\Collection<int, Location>  $locations
     * @return array<int, array{a: Location, b: Location, distance: ?float, reason: string}>
     */
    private function nearDuplicateSites($locations): array
    {
        $pairs = [];
        $list  = $locations->values();

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                $a = $list[$i];
                $b = $list[$j];

                $nameA = $this->normaliseName($a->name);
                $nameB = $this->normaliseName($b->name);

                $distance = $this->distanceMeters(
                    (float) $a->latitude,
                    (float) $a->longitude,
                    (float) $b->latitude,
                    (float) $b->longitude
                );

                $reason = null;

                if ($nameA !== '' && $nameA === $nameB) {
                    $reason = 'same name';
                } elseif (
                    $nameA !== '' && $nameB !== ''
                    && (str_starts_with($nameA, $nameB) || str_starts_with($nameB, $nameA))
                ) {
                    $reason = 'one name starts with the other';
                } elseif ($distance !== null && $distance <= 250) {
                    $reason = 'less than 250 m apart';
                }

                if ($reason !== null) {
                    $pairs[] = ['a' => $a, 'b' => $b, 'distance' => $distance, 'reason' => $reason];
                }
            }
        }

        return $pairs;
    }

    /**
     * One site shared by branches from different cities is almost always a
     * wrong mapping ("Adama Branch -> figa").
     *
     * @param  \Illuminate\Support\Collection<int, Branch>  $branches
     * @return array<int, array{location: Location, cities: array<int, string>, branches: array<int, string>}>
     */
    private function mixedCitySites($branches): array
    {
        $groups = [];

        foreach ($branches as $branch) {
            if (! $branch->location_id || ! $branch->location) {
                continue;
            }

            $city = trim((string) $branch->city);

            if ($city === '') {
                continue;
            }

            $key = $branch->location_id;

            $groups[$key]['location'] = $branch->location;
            $groups[$key]['cities'][mb_strtolower($city)] = $city;
            $groups[$key]['branches'][] = $branch->name;
        }

        return array_values(array_filter($groups, function ($group) {
            return count($group['cities']) > 1;
        }));
    }

    private function normaliseName(?string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $name)) ?? '';
    }

    /**
     * Great-circle distance in metres, or null when coordinates are missing.
     */
    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): ?float
    {
        if (($lat1 === 0.0 && $lng1 === 0.0) || ($lat2 === 0.0 && $lng2 === 0.0)) {
            return null;
        }

        $earthRadius = 6371000.0;
        $latDelta    = deg2rad($lat2 - $lat1);
        $lngDelta    = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Expand a pasted Google Maps link (long or short) into coordinates.
     *
     * Short share links such as https://maps.app.goo.gl/xxxx are HTTP
     * redirects that the browser cannot read cross-origin, so the redirect
     * chain is followed here instead. No Google API key is involved.
     */
    public function resolveLink(Request $request, GoogleMapsLinkResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'link' => 'required|string|max:2000',
        ]);

        try {
            $coordinates = $resolver->resolve($data['link']);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success'      => true,
            'latitude'     => round($coordinates['latitude'], 7),
            'longitude'    => round($coordinates['longitude'], 7),
            'resolved_url' => $coordinates['resolved_url'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'address'   => 'nullable|string|max:255',
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_m'  => 'required|integer|min:10|max:5000',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }
}

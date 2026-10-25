<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\EmployeeLocationAssignment;
use App\Models\Location;
use App\Models\User;
use App\Services\Attendance\AttendanceCalendar;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Three HR views over the attendance data:
 * per branch, per department, and one employee's full story.
 */
class AttendanceReportController extends Controller
{
    public function __construct(protected AttendanceCalendar $calendar) {}

    // ============================================================
    //  PER BRANCH
    // ============================================================
    public function branch(Request $request)
    {
        [$from, $to] = $this->range($request);
        $lateAfter   = $this->lateAfter($request);
        $startTime   = $this->startTime($request);
        $grace       = $this->grace($request);

        $branchId     = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $departmentId = $request->filled('department_id') ? (int) $request->input('department_id') : null;
        $search       = trim((string) $request->input('q'));

        $sort = (string) $request->input('sort', 'absent');

        if (! in_array($sort, ['absent', 'late', 'hours', 'name'], true)) {
            $sort = 'absent';
        }

        $workingDays = $this->workingDays($from, $this->expectedUntil($to));

        // The status cards always describe the whole scope. The card the user
        // clicks only narrows the rows underneath, never the numbers above.
        $status = $this->statusFilter($request);

        $employees = $this->prepare(
            $this->aggregates($from, $to, $branchId, $departmentId, $search, $lateAfter),
            $from,
            $to
        );

        $totals    = $this->totals($employees);
        $employees = $this->filterByStatus($employees, $status);

        // One group per branch: the page is a branch summary first, with the
        // employee rows revealed underneath each branch.
        $groups = $employees
            ->groupBy(fn($row) => $row->branch_name ?: 'No branch')
            ->map(function ($group, $name) use ($sort) {
                $rows = match ($sort) {
                    'late'  => $group->sortByDesc('late_days'),
                    'hours' => $group->sortByDesc('total_hours'),
                    'name'  => $group->sortBy('user_name'),
                    default => $group->sortByDesc('days_absent'),
                };

                $present  = (int) $group->sum('days_present');
                $absent   = round((float) $group->sum('days_absent'), 2);
                $expected = $present + $absent;

                return (object) [
                    'key'             => 'branch-' . substr(md5($name), 0, 8),
                    'branch_name'     => $name,
                    'headcount'       => $group->count(),
                    'departments'     => $group->pluck('department_name')->filter()->unique()->count(),
                    'rows'            => $rows->values(),
                    'days_present'    => $present,
                    'days_absent'     => $absent,
                    'leave_days'      => round((float) $group->sum('leave_days'), 2),
                    'late_days'       => (int) $group->sum('late_days'),
                    'days_open'       => (int) $group->sum('days_open'),
                    'on_duty_days'    => (int) $group->sum('on_duty_days'),
                    'total_hours'     => round((float) $group->sum('total_hours'), 2),
                    'overtime_hours'  => round((float) $group->sum('overtime_hours'), 2),
                    'never_recorded'  => $group->where('days_present', 0)->count(),
                    'attendance_rate' => $expected > 0 ? round($present / $expected * 100, 1) : null,
                ];
            });

        $groups = match ($sort) {
            'name'  => $groups->sortBy('branch_name'),
            'late'  => $groups->sortByDesc('late_days'),
            'hours' => $groups->sortByDesc('total_hours'),
            default => $groups->sortByDesc('days_absent'),
        };

        $groups = $groups->values();

        // Figures for the footer of the filtered table.
        $filteredTotals = $this->totals($employees);

        $branches    = Branch::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('department')->get(['id', 'department as department_name']);

        return view('attendance.reports.branch', compact(
            'groups',
            'totals',
            'filteredTotals',
            'status',
            'branches',
            'departments',
            'from',
            'to',
            'workingDays',
            'lateAfter',
            'startTime',
            'grace',
            'branchId',
            'departmentId',
            'search',
            'sort'
        ));
    }

    // ============================================================
    //  PER DEPARTMENT
    // ============================================================
    public function department(Request $request)
    {
        [$from, $to] = $this->range($request);
        $lateAfter   = $this->lateAfter($request);
        $startTime   = $this->startTime($request);
        $grace       = $this->grace($request);

        $branchId     = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;
        $departmentId = $request->filled('department_id') ? (int) $request->input('department_id') : null;
        $search       = trim((string) $request->input('q'));

        $sort = (string) $request->input('sort', 'absent');

        if (! in_array($sort, ['absent', 'late', 'hours', 'name'], true)) {
            $sort = 'absent';
        }

        $workingDays = $this->workingDays($from, $this->expectedUntil($to));

        // The status cards always describe the whole scope. The card the user
        // clicks only narrows the rows underneath, never the numbers above.
        $status = $this->statusFilter($request);

        $employees = $this->prepare(
            $this->aggregates($from, $to, $branchId, $departmentId, $search, $lateAfter),
            $from,
            $to
        );

        $totals    = $this->totals($employees);
        $employees = $this->filterByStatus($employees, $status);

        $groups = $employees
            ->groupBy(fn($row) => $row->department_name ?: 'No department')
            ->map(function ($group, $name) use ($sort) {
                $rows = match ($sort) {
                    'late'  => $group->sortByDesc('late_days'),
                    'hours' => $group->sortByDesc('total_hours'),
                    'name'  => $group->sortBy('user_name'),
                    default => $group->sortByDesc('days_absent'),
                };

                $present = (int) $group->sum('days_present');
                $absent  = round((float) $group->sum('days_absent'), 2);
                $expected = $present + $absent;

                return (object) [
                    'key'             => 'dept-' . substr(md5($name), 0, 8),
                    'department_name' => $name,
                    'department_id'   => $group->first()->department_id ?? null,
                    'headcount'       => $group->count(),
                    'rows'            => $rows->values(),
                    'days_present'    => $present,
                    'days_absent'     => $absent,
                    'leave_days'      => round((float) $group->sum('leave_days'), 2),
                    'late_days'       => (int) $group->sum('late_days'),
                    'on_duty_days'    => (int) $group->sum('on_duty_days'),
                    'total_hours'     => round($group->sum('total_hours'), 2),
                    'overtime_hours'  => round($group->sum('overtime_hours'), 2),
                    'never_recorded'  => $group->where('days_present', 0)->count(),
                    'attendance_rate' => $expected > 0 ? round($present / $expected * 100, 1) : null,
                ];
            });

        $groups = match ($sort) {
            'name'  => $groups->sortBy('department_name'),
            'late'  => $groups->sortByDesc('late_days'),
            'hours' => $groups->sortByDesc('total_hours'),
            default => $groups->sortByDesc('days_absent'),
        };

        $groups = $groups->values();

        // Figures for the footer of the filtered table.
        $filteredTotals = $this->totals($employees);

        $branches    = Branch::orderBy('name')->get(['id', 'name']);
        $departments = Department::orderBy('department')->get(['id', 'department as department_name']);

        return view('attendance.reports.department', compact(
            'groups',
            'totals',
            'filteredTotals',
            'status',
            'branches',
            'departments',
            'from',
            'to',
            'workingDays',
            'lateAfter',
            'startTime',
            'grace',
            'branchId',
            'departmentId',
            'search',
            'sort'
        ));
    }

    // ============================================================
    //  ONE EMPLOYEE'S FULL STORY
    // ============================================================
    public function user(Request $request, User $user)
    {
        $viewer = auth()->user();

        // Everyone may open their own story; somebody else's full history is
        // reserved for the report roles (the route group no longer blocks it,
        // because the monthly sheet links every employee to their own row).
        abort_unless(
            $viewer && ($viewer->id === $user->id || $viewer->hasAnyRole(['Admin', 'Super Admin', 'HR'])),
            403,
            'You can only open your own attendance report.'
        );

        [$from, $to] = $this->range($request);
        $lateAfter   = $this->lateAfter($request);
        $startTime   = $this->startTime($request);
        $grace       = $this->grace($request);

        $user->load(['branch.location', 'department', 'location']);

        $attendance = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('date')
            ->get();

        // Flag late arrivals once so the table and the counters agree.
        $attendance->each(function ($record) use ($lateAfter) {
            $record->is_late = $record->clock_in !== null
                && substr((string) $record->clock_in, 0, 8) > $lateAfter;
        });

        $locationIds = $attendance->pluck('clock_in_location_id')
            ->merge($attendance->pluck('clock_out_location_id'))
            ->merge($attendance->pluck('assigned_location_id'))
            ->merge($attendance->pluck('location_id'))
            ->filter()
            ->unique()
            ->values();

        $locations = Location::whereIn('id', $locationIds)->get()->keyBy('id');

        // Resolve the branch an on-duty punch was taken at, so the table can
        // name it instead of printing a raw id.
        $dutyBranchIds = $attendance->pluck('duty_branch_id')->filter()->unique()->values();

        $dutyBranches = $dutyBranchIds->isEmpty()
            ? collect()
            : Branch::whereIn('id', $dutyBranchIds)->pluck('name', 'id');

        $assignedLocation = $user->attendanceLocation();
        $source           = $user->attendanceLocationSource();

        $postings = EmployeeLocationAssignment::with('location')
            ->forUser($user->id)
            ->orderByDesc('starts_on')
            ->get();

        $summary = (object) [
            'days_present'   => $attendance->whereNotNull('clock_in')->count(),
            'days_open'      => $attendance->whereNull('clock_out')->count(),
            'on_duty_days'   => $attendance->filter(function ($row) {
                return $row->clock_in_match === 'on_duty' || $row->clock_out_match === 'on_duty';
            })->count(),
            'late_days'      => $attendance->where('is_late', true)->count(),
            'duty_notes'     => $attendance->whereNotNull('duty_note')->count(),
            'total_hours'    => round((float) $attendance->sum('total_hours'), 2),
            'break_hours'    => round((float) $attendance->sum('break_hours'), 2),
            'overtime_hours' => round((float) $attendance->sum('overtime_hours'), 2),
        ];

        $joinedOn = $this->parseJoinDate($user->join_date);

        // Working days only start counting from the day the person joined.
        $start = $from->copy();

        if ($joinedOn && $joinedOn->gt($start)) {
            $start = $joinedOn->copy();
        }

        $workingDays = $start->gt($to) ? 0 : $this->workingDays($start, $this->expectedUntil($to));

        // A day that has a punch is never excused by a leave.
        $presentDates = $attendance->whereNotNull('clock_in')
            ->mapWithKeys(fn($row) => [Carbon::parse($row->date)->toDateString() => true])
            ->all();

        $leave    = $this->calendar->leaveWorkingDays($user->id, $start, $this->expectedUntil($to), $presentDates);
        $required = max(0.0, $workingDays - $leave);

        $summary->expected        = $workingDays;
        $summary->leave_days      = $leave;
        $summary->days_required   = round($required, 2);
        $summary->days_absent     = round(max(0.0, $workingDays - $summary->days_present - $leave), 2);
        $summary->attendance_rate = $required > 0
            ? round(min($summary->days_present, $required) / $required * 100, 1)
            : null;

        return view('attendance.reports.user', compact(
            'user',
            'attendance',
            'locations',
            'dutyBranches',
            'assignedLocation',
            'source',
            'postings',
            'summary',
            'from',
            'to',
            'workingDays',
            'joinedOn',
            'lateAfter',
            'startTime',
            'grace'
        ));
    }

    // ============================================================
    //  HELPERS
    // ============================================================

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))
            : Carbon::now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))
            : Carbon::now();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->copy()->startOfDay(), $to->copy()->startOfDay()];
    }

    /**
     * The shift start the report measures lateness from.
     */
    private function startTime(Request $request): string
    {
        $start = (string) $request->input('start_time', '08:30');

        try {
            return Carbon::parse($start)->format('H:i');
        } catch (\Throwable $e) {
            return '08:30';
        }
    }

    /**
     * Minutes of grace added on top of the shift start.
     */
    private function grace(Request $request): int
    {
        return max(0, min(120, (int) $request->input('grace', 15)));
    }

    /**
     * Clock-in time later than this counts as late.
     */
    private function lateAfter(Request $request): string
    {
        return Carbon::parse($this->startTime($request))
            ->addMinutes($this->grace($request))
            ->format('H:i:s');
    }

    /**
     * Expected working days: Monday to Saturday, minus public holidays.
     */
    private function workingDays(Carbon $from, Carbon $to): int
    {
        return $this->calendar->workingDays($from, $to);
    }

    /**
     * Nobody can be absent in the future, so the expected-days window always
     * stops at today even when the person picked a later end date.
     */
    private function expectedUntil(Carbon $to): Carbon
    {
        $today = Carbon::today();

        return $to->gt($today) ? $today : $to;
    }

    /**
     * One row per ACTIVE employee, whether or not they ever punched in.
     *
     * The report starts from `users` and LEFT JOINs attendance, so somebody
     * who never showed up is still listed (with zero present days) instead of
     * silently disappearing. Only users with status "Active" and no soft
     * delete are included.
     *
     * The department axis is the user's department (`users.department_id` →
     * `departments`).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function aggregates(
        Carbon $from,
        Carbon $to,
        ?int $branchId,
        ?int $departmentId,
        string $search,
        string $lateAfter
    ) {
        return DB::table('users as u')
            ->leftJoin('attendances as a', function ($join) use ($from, $to) {
                $join->on('a.user_id', '=', 'u.id')
                    ->whereBetween('a.date', [$from->toDateString(), $to->toDateString()]);
            })
            ->leftJoin('branches as b', 'b.id', '=', 'u.branch_id')
            ->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->where('u.status', 'Active')
            ->whereNull('u.deleted_at')
            ->when($branchId, function ($query) use ($branchId) {
                $query->where('u.branch_id', $branchId);
            })
            ->when($departmentId, function ($query) use ($departmentId) {
                $query->where('u.department_id', $departmentId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('u.name', 'like', "%{$search}%");
            })
            ->groupBy('u.id', 'u.name', 'u.join_date', 'u.department_id', 'b.name', 'd.department')
            ->selectRaw(
                "u.id as user_id,\n"
                    . "u.name as user_name,\n"
                    . "u.join_date as join_date,\n"
                    . "b.name as branch_name,\n"
                    . "d.department as department_name,\n"
                    . "u.department_id as department_id,\n"
                    . "COUNT(DISTINCT CASE WHEN a.clock_in IS NOT NULL THEN a.date END) as days_present,\n"
                    . "COALESCE(SUM(a.total_hours), 0) as total_hours,\n"
                    . "COALESCE(SUM(a.overtime_hours), 0) as overtime_hours,\n"
                    . "COALESCE(SUM(a.break_hours), 0) as break_hours,\n"
                    . "COUNT(DISTINCT CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN a.date END) as days_open,\n"
                    . "COUNT(DISTINCT CASE WHEN a.clock_in_match = 'on_duty' OR a.clock_out_match = 'on_duty' THEN a.date END) as on_duty_days,\n"
                    . "COUNT(DISTINCT CASE WHEN a.clock_in > ? THEN a.date END) as late_days",
                [$lateAfter]
            )
            ->orderBy('u.name')
            ->get()
            ->map(function ($row) {
                $row->days_present   = (int) $row->days_present;
                $row->days_open      = (int) $row->days_open;
                $row->on_duty_days   = (int) $row->on_duty_days;
                $row->late_days      = (int) $row->late_days;
                $row->total_hours    = round((float) $row->total_hours, 2);
                $row->overtime_hours = round((float) $row->overtime_hours, 2);
                $row->break_hours    = round((float) $row->break_hours, 2);

                return $row;
            });
    }

    /**
     * Decorates every aggregate row and drops anyone who had not been hired
     * yet inside the selected range.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function prepare($rows, Carbon $from, Carbon $to)
    {
        $until   = $this->expectedUntil($to);
        $userIds = $rows->pluck('user_id')->all();

        // Prime both lookups for the whole page instead of querying per row.
        $this->calendar->primeLeaves($userIds, $from, $until);
        $presentDates = $this->attendanceDatesByUser($userIds, $from, $until);

        return $rows
            ->map(function ($row) use ($from, $to, $presentDates) {
                return $this->decorate($row, $from, $to, $presentDates[(int) $row->user_id] ?? []);
            })
            ->filter()
            ->values();
    }

    /**
     * Dates each employee actually attended. Used so a punch always wins over
     * a leave on the same day.
     *
     * @param  array<int, int|string>  $userIds
     * @return array<int, array<string, true>>
     */
    private function attendanceDatesByUser(array $userIds, Carbon $from, Carbon $to): array
    {
        if ($userIds === []) {
            return [];
        }

        return Attendance::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('clock_in')
            ->get(['user_id', 'date'])
            ->groupBy('user_id')
            ->map(function ($rows) {
                return $rows->mapWithKeys(function ($row) {
                    return [Carbon::parse($row->date)->toDateString() => true];
                })->all();
            })
            ->all();
    }

    /**
     * Adds the derived numbers used by the tables. Working days are counted
     * from the employee's join date, so a new hire is not shown as absent for
     * the months before they started. Returns null when the person had not
     * joined yet within the range.
     */
    private function decorate(object $row, Carbon $from, Carbon $to, array $presentDates = []): ?object
    {
        $start  = $from->copy();
        $joined = $this->parseJoinDate($row->join_date ?? null);

        if ($joined) {
            if ($joined->gt($to)) {
                return null;
            }

            if ($joined->gt($start)) {
                $start = $joined;
            }
        }

        $until    = $this->expectedUntil($to);
        $expected = $this->workingDays($start, $until);

        // Approved leave on a working day is excused, so it is neither
        // expected nor absent. Public holidays are already out of $expected.
        $leave    = $this->calendar->leaveWorkingDays((int) $row->user_id, $start, $until, $presentDates);
        $required = max(0.0, $expected - $leave);

        $row->joined_on       = $joined ? $joined->toDateString() : null;
        $row->working_days    = $expected;
        $row->leave_days      = $leave;
        $row->days_required   = round($required, 2);
        $row->days_absent     = round(max(0.0, $expected - $row->days_present - $leave), 2);
        $row->avg_hours       = $row->days_present > 0
            ? round($row->total_hours / $row->days_present, 2)
            : 0.0;
        $row->attendance_rate = $required > 0
            ? round(min($row->days_present, $required) / $required * 100, 1)
            : null;

        return $row;
    }

    /**
     * `users.join_date` is a free-text column holding values such as
     * "Wed, Jun 11, 2025 12:00 AM" next to "2025-07-16 11:12:32". Parsed
     * defensively (see DateHelper::parseLooseDate) so one malformed row cannot
     * break the whole report.
     */
    private function parseJoinDate($value): ?Carbon
    {
        return DateHelper::parseLooseDate($value);
    }

    /**
     * Which status card, if any, is narrowing the report. Unknown values fall
     * back to "all" so a hand-edited URL can never break the page.
     */
    private function statusFilter(Request $request): string
    {
        $status = (string) $request->input('status', 'all');

        return in_array($status, ['all', 'present', 'absent', 'late', 'on_duty'], true)
            ? $status
            : 'all';
    }

    /**
     * Keeps only the employees who contributed to the clicked card. "Present"
     * means at least one recorded day, "absent" at least one missing working
     * day, and so on.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function filterByStatus($rows, string $status)
    {
        if ($status === 'all') {
            return $rows;
        }

        return $rows
            ->filter(function ($row) use ($status) {
                return match ($status) {
                    'present' => $row->days_present > 0,
                    'absent'  => $row->days_absent > 0,
                    'late'    => $row->late_days > 0,
                    'on_duty' => $row->on_duty_days > 0,
                    default   => true,
                };
            })
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     */
    private function totals($rows): object
    {
        $present  = (int) $rows->sum('days_present');
        $absent   = round((float) $rows->sum('days_absent'), 2);
        $leave    = round((float) $rows->sum('leave_days'), 2);
        // $absent already excludes approved leave, so this is the required total.
        $expected = $present + $absent;

        return (object) [
            'employees'       => $rows->count(),
            'branches'        => $rows->groupBy(fn($row) => $row->branch_name ?: 'No branch')->count(),
            'departments'     => $rows->groupBy(fn($row) => $row->department_name ?: 'No department')->count(),
            'days_present'    => $present,
            'days_absent'     => $absent,
            'leave_days'      => $leave,
            'late_days'       => (int) $rows->sum('late_days'),
            'days_open'       => (int) $rows->sum('days_open'),
            'on_duty_days'    => (int) $rows->sum('on_duty_days'),
            'total_hours'     => round((float) $rows->sum('total_hours'), 2),
            'overtime_hours'  => round((float) $rows->sum('overtime_hours'), 2),
            'never_recorded'  => $rows->where('days_present', 0)->count(),
            'attendance_rate' => $expected > 0
                ? round($present / $expected * 100, 1)
                : null,
        ];
    }
}

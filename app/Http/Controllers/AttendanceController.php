<?php

namespace App\Http\Controllers;

use App\Helpers\DateHelper;
use App\Models\Attendance;
use App\Models\AttendanceQrCode;
use App\Models\User;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Location;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceCalendar;
use App\Services\Attendance\GeofenceService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function __construct(
        protected GeofenceService $geofence,
        protected AttendanceCalculator $calculator,
        protected AttendanceCalendar $calendar
    ) {}

    // ============================================================
    //  INDEX  — timesheet + history + filters
    // ============================================================
    public function index(Request $request)
    {
        $user  = Auth::user();
        $today = Carbon::today()->toDateString();

        // ---------- Today ----------
        $todayAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        // ---------- Week summary ----------
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd   = Carbon::now()->endOfWeek();
        $weekAttendance = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->get();
        $weekTotal    = $weekAttendance->sum('total_hours');
        $weekBreak    = $weekAttendance->sum('break_hours');
        $weekOvertime = $weekAttendance->sum('overtime_hours');

        // ---------- Month summary ----------
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd   = Carbon::now()->endOfMonth();
        $monthAttendance = Attendance::where('user_id', $user->id)
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->get();
        $monthTotal    = $monthAttendance->sum('total_hours');
        $monthBreak    = $monthAttendance->sum('break_hours');
        $monthOvertime = $monthAttendance->sum('overtime_hours');

        // ---------- History with filters ----------
        // A first visit (and Reset) opens on the current month. Picking
        // "All months" / "All years" submits the empty values, which widens the
        // listing instead of falling back to the default. A date is precise on
        // its own, so month and year only apply when no date is given.
        $hasDate = $request->filled('date');
        $ranged  = $request->hasAny(['date', 'month', 'year']);

        $month = $hasDate
            ? null
            : ($request->filled('month') ? (int) $request->input('month') : ($ranged ? null : (int) now()->month));

        $year = $hasDate
            ? null
            : ($request->filled('year') ? (int) $request->input('year') : ($ranged ? null : (int) now()->year));

        $query = Attendance::where('user_id', $user->id);

        if ($hasDate) {
            $query->whereDate('date', $request->date);
        }
        if ($month) {
            $query->whereMonth('date', $month);
        }
        if ($year) {
            $query->whereYear('date', $year);
        }

        $attendances = $query->orderBy('date', 'desc')
            ->paginate(10)
            ->appends($request->query());

        // Geofence this employee must punch from (user -> branch -> default).
        $attendanceLocation = $user->attendanceLocation();

        // Every active site is punchable: the assigned one is silent, any other
        // one is recorded as "on duty" and needs a reason for HR.
        $punchSites = Location::query()->active()->orderBy('name')->get();
        $canPunch   = $attendanceLocation !== null || $punchSites->isNotEmpty();

        $dutyReasons = ['Branch duty', 'Field duty', 'Client visit', 'Other'];

        // ============================================================
        //  QR objects — same style as training topics
        //  Pre-generated server-side so the Blade can render <img>.
        // ============================================================
        $checkInQr  = null;
        $checkOutQr = null;

        try {
            // Show check-in QR unless the user has already clocked out today
            if (!$todayAttendance || !$todayAttendance->clock_out) {
                $checkInQr = AttendanceQrCode::freshFor($user->id, 'in');
            }

            // Show check-out QR only while the user is currently working
            if ($todayAttendance && !$todayAttendance->clock_out) {
                $checkOutQr = AttendanceQrCode::freshFor($user->id, 'out');
            }
        } catch (\Throwable $e) {
            Log::warning('QR pre-generation failed for user ' . $user->id . ': ' . $e->getMessage());
        }

        return view('attendance.index', compact(
            'todayAttendance',
            'weekTotal',
            'weekBreak',
            'weekOvertime',
            'monthTotal',
            'monthBreak',
            'monthOvertime',
            'attendances',
            'month',
            'year',
            'attendanceLocation',
            'punchSites',
            'canPunch',
            'dutyReasons',
            'checkInQr',
            'checkOutQr'
        ));
    }

    // ============================================================
    //  PUNCH IN (manual)
    // ============================================================
    public function punchIn(Request $request)
    {
        $data = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'required|numeric|min:0',
            'duty_note' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $expected = $user->attendanceLocation();
        $sites = $this->punchSites();

        if (! $expected && $sites->isEmpty()) {
            return $this->locationNotConfigured();
        }

        $check = $this->geofence->evaluate(
            $expected,
            $sites,
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $data['accuracy']
        );

        if (! $check['allowed']) {
            Toastr::error($check['message'], 'Cannot Punch In');
            return back()->withErrors(['location' => $check['message']]);
        }

        $note = trim((string) $request->input('duty_note', ''));

        if ($check['match'] === 'on_duty' && $note === '') {
            $message = sprintf(
                'You are at %s, not at your assigned site. Write a short reason so HR can see why.',
                $check['location_name']
            );

            Toastr::warning($message, 'Reason Required');

            return back()->withErrors(['duty_note' => $message])->withInput();
        }

        $today = now()->toDateString();

        if (Attendance::where('user_id', $user->id)->whereDate('date', $today)->exists()) {
            Toastr::warning('You have already punched in today.', 'Already Punched In');
            return back()->with('warning', 'You have already punched in today.');
        }

        $matched = $check['matched_location'];

        try {
            Attendance::create([
                'user_id'             => $user->id,
                'location_id'         => $matched->id,
                'assigned_location_id' => $expected?->id,
                'clock_in_location_id' => $matched->id,
                'clock_in_match'      => $check['match'],
                'duty_branch_id'      => $check['match'] === 'on_duty' ? $this->dutyBranchId($matched, $user) : null,
                'duty_note'           => $note !== '' ? $note : null,
                'date'                => $today,
                'clock_in'            => now()->toTimeString(),
                'clock_in_image'      => $request->image,
                'clock_in_distance_m' => (int) round($check['distance_m']),
                'clock_in_accuracy_m' => $check['accuracy_m'],
                'ip_address'          => $request->ip(),
                'location'            => $data['latitude'] . ',' . $data['longitude'],
                'status'              => 'present',
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            // Unique (user_id, date) index - double submit or race.
            Toastr::warning('You have already punched in today.', 'Already Punched In');
            return back()->with('warning', 'You have already punched in today.');
        }

        Toastr::success($this->punchMessage('Punched in', $check), 'Success');
        return back()->with('success', 'Punched in successfully');
    }

    // ============================================================
    //  PUNCH OUT (manual)
    // ============================================================
    public function punchOut(Request $request)
    {
        $data = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'required|numeric|min:0',
        ]);

        $attendance = Attendance::where('user_id', Auth::id())
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance) {
            Toastr::error('You have not punched in today.', 'Not Punched In');
            return back()->withErrors(['location' => 'You have not punched in today.']);
        }

        if ($attendance->clock_out) {
            Toastr::warning('You have already punched out today.', 'Already Punched Out');
            return back()->with('warning', 'You have already punched out today.');
        }

        // Keep the site the shift started on, even if HR reassigns mid-day.
        $location = $attendance->clock_in_location_id
            ? Location::find($attendance->clock_in_location_id)
            : Auth::user()->attendanceLocation();

        $sites = $this->punchSites();

        if (! $location && $sites->isEmpty()) {
            return $this->locationNotConfigured();
        }

        $check = $this->geofence->evaluate(
            $location,
            $sites,
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $data['accuracy']
        );

        if (! $check['allowed']) {
            Toastr::error($check['message'], 'Cannot Punch Out');
            return back()->withErrors(['location' => $check['message']]);
        }

        $note = trim((string) $request->input('duty_note', ''));

        if ($check['match'] === 'on_duty' && $note === '') {
            $message = sprintf(
                'You are at %s, not where your shift started. Write a short reason so HR can see why.',
                $check['location_name']
            );

            Toastr::warning($message, 'Reason Required');

            return back()->withErrors(['duty_note' => $message])->withInput();
        }

        $matched = $check['matched_location'];

        $attendance->fill([
            'clock_out'             => now()->toTimeString(),
            'clock_out_image'       => $request->image,
            'location_id'           => $matched->id,
            'clock_out_location_id' => $matched->id,
            'clock_out_match'       => $check['match'],
            'duty_branch_id'        => $attendance->duty_branch_id
                ?: ($check['match'] === 'on_duty' ? $this->dutyBranchId($matched, Auth::user()) : null),
            'duty_note'             => $attendance->duty_note ?: ($note !== '' ? $note : null),
            'clock_out_distance_m'  => (int) round($check['distance_m']),
            'clock_out_accuracy_m'  => $check['accuracy_m'],
        ]);

        // Fill the new times first, then calculate: never use a stale instance.
        $attendance->fill($this->calculator->totals($attendance));
        $attendance->save();

        Toastr::success($this->punchMessage('Punched out', $check), 'Success');
        return back()->with('success', 'Punched out successfully');
    }

    // ============================================================
    //  BREAK START
    // ============================================================
    public function breakStart(Request $request)
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance) {
            return response()->json([
                'success' => false,
                'message' => 'You have not punched in today.',
            ], 404);
        }

        if ($attendance->clock_out) {
            return response()->json([
                'success' => false,
                'message' => 'You have already clocked out.',
            ], 400);
        }

        if ($attendance->break_start && !$attendance->break_end) {
            return response()->json([
                'success' => false,
                'message' => 'Break already in progress.',
            ], 400);
        }

        if ($attendance->break_start && $attendance->break_end) {
            return response()->json([
                'success' => false,
                'message' => 'You have already taken your break today.',
            ], 400);
        }

        $attendance->update([
            'break_start' => now()->toTimeString(),
        ]);

        return response()->json(['success' => true]);
    }

    // ============================================================
    //  BREAK END
    // ============================================================
    public function breakEnd(Request $request)
    {
        $attendance = Attendance::where('user_id', auth()->id())
            ->whereDate('date', now()->toDateString())
            ->first();

        if (! $attendance) {
            return response()->json([
                'success' => false,
                'message' => 'You have not punched in today.',
            ], 404);
        }

        if (!$attendance->break_start) {
            return response()->json([
                'success' => false,
                'message' => 'No break in progress.',
            ], 400);
        }

        if ($attendance->break_end) {
            return response()->json([
                'success' => false,
                'message' => 'Break already ended.',
            ], 400);
        }

        $attendance->fill([
            'break_end' => now()->toTimeString(),
        ]);

        // Fill break_end first so the totals see the new value.
        $totals = $this->calculator->totals($attendance);
        $attendance->fill($totals);
        $attendance->save();

        return response()->json([
            'success' => true,
            'break_hours' => $totals['break_hours'],
            'total_hours' => $totals['total_hours'],
        ]);
    }

    // ============================================================
    //  FILTER  — redirects to index with query params applied
    // ============================================================
    public function filter(Request $request)
    {
        return redirect()->route('attendance.index', $request->only(['date', 'month', 'year']));
    }

    // ============================================================
    //  MONTHLY ATTENDANCE GRID  — role-based access
    // ============================================================
    public function attendanceIndex(Request $request)
    {
        $user          = Auth::user();

        // The filter form can submit empty strings, which would blow up
        // Carbon::create(). Cast defensively and fall back to this month.
        $selectedMonth = (int) $request->input('month', date('n'));
        $selectedYear  = (int) $request->input('year', date('Y'));

        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = (int) date('n');
        }

        if ($selectedYear < 2000 || $selectedYear > 2100) {
            $selectedYear = (int) date('Y');
        }

        $employeeName  = $request->input('employee_name');

        // Requested department filter (users.department_id).
        $filterDepartment = $request->input('department');

        // The sheet is a "who is supposed to be here" list, so it hides
        // deactivated accounts unless the viewer asks for them.
        $includeInactive = $request->boolean('include_inactive');

        $startOfMonth = Carbon::create($selectedYear, $selectedMonth, 1)->startOfMonth();
        $endOfMonth   = Carbon::create($selectedYear, $selectedMonth, 1)->endOfMonth();
        $daysInMonth  = $startOfMonth->daysInMonth;

        $employeeQuery = User::query();

        // --- Role-Based Access Control ---
        if ($user->hasAnyRole(['Admin', 'Super Admin', 'HR', 'CEO'])) {
            // Full access
        } elseif (Branch::where('manager_id', $user->id)->exists()) {
            $managerBranch = Branch::where('manager_id', $user->id)->first();
            $employeeQuery->where('branch_id', $managerBranch->id);
        } elseif ($user->is_representative == 1 || $user->is_department_representative == 1) {
            // A representative's slice is their branch plus their department —
            // the same axis the Department filter on this sheet uses.
            if ($user->branch_id) {
                $employeeQuery->where('branch_id', $user->branch_id);
            }

            if ($user->department_id) {
                $employeeQuery->where(function ($q) use ($user) {
                    $q->where('department_id', $user->department_id)
                        ->orWhere('id', $user->id);
                });
            } else {
                // Nothing to scope by: at least show the representative their
                // own row instead of an empty sheet.
                $employeeQuery->where('id', $user->id);
            }
        } elseif ($user->hasRole('Employee')) {
            $employeeQuery->where('id', $user->id);
        } else {
            $employeeQuery->whereRaw('1 = 0');
        }

        if (!empty($employeeName)) {
            $employeeQuery->where('name', 'like', '%' . $employeeName . '%');
        }

        // Department filter (users.department_id).
        if (!empty($filterDepartment)) {
            $employeeQuery->where('department_id', $filterDepartment);
        }

        // Active accounts only by default; same case-insensitive comparison
        // the row badge uses, so NULL/legacy values never drop a real person.
        if (!$includeInactive) {
            $employeeQuery->whereRaw('LOWER(users.status) = ?', ['active']);
        }

        $employees = $employeeQuery->get();
        $employeeIds = $employees->pluck('id')->all();

        // Holidays are normalised in PHP: date_holiday is a varchar holding
        // mixed formats ("11-09-2025" next to "2026-01-07 00:00:00"), and
        // MONTH()/YEAR() return NULL for the dd-mm-yyyy rows, which silently
        // dropped those holidays from the grid.
        $holidaySet = array_flip($this->calendar->holidays());

        // One query for the whole month instead of one per employee, keyed
        // user => 'Y-m-d' => attendance row.
        $attendancesByUser = $employeeIds === []
            ? collect()
            : Attendance::query()
            ->whereIn('user_id', $employeeIds)
            ->whereBetween('date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->groupBy('user_id')
            ->map(fn($rows) => $rows->keyBy(
                fn($row) => Carbon::parse($row->date)->toDateString()
            ));

        // One query for the approved leaves of everyone on screen.
        $this->calendar->primeLeaves($employeeIds, $startOfMonth, $endOfMonth);

        $today = Carbon::today();

        foreach ($employees as $employee) {
            $attendanceData = [];
            $byDate = $attendancesByUser->get($employee->id) ?? collect();

            // Approved leave only, per date. Pending or disapproved requests
            // must still show up as absences.
            $leaveDates = $this->calendar->approvedLeaveDates(
                $employee->id,
                $startOfMonth,
                $endOfMonth
            );

            // join_date is free text ("Wed, Apr 29, 2026 12:00 AM"), so read the
            // raw column and parse it defensively.
            $joinedOn = DateHelper::parseLooseDate($employee->getRawOriginal('join_date'));

            for ($day = 1; $day <= $daysInMonth; $day++) {
                $date    = Carbon::create($selectedYear, $selectedMonth, $day);
                $dateStr = $date->toDateString();

                $attendance = $byDate->get($dateStr);
                $punchedIn  = $attendance && $attendance->clock_in !== null;

                $isHoliday = isset($holidaySet[$dateStr]);
                $onLeave   = isset($leaveDates[$dateStr]);

                // A day that has not happened yet, or one from before the
                // person was hired, cannot be an absence.
                if ($date->isSunday()) {
                    $status = 'sunday';
                } elseif ($punchedIn) {
                    $status = $date->isSaturday() ? 'saturday_present' : 'present';
                } elseif ($isHoliday) {
                    $status = 'holiday';
                } elseif ($onLeave) {
                    $status = 'leave';
                } elseif ($date->gt($today)) {
                    $status = 'future';
                } elseif ($joinedOn && $date->lt($joinedOn)) {
                    $status = 'before_employment';
                } else {
                    $status = 'absent';
                }

                $attendanceData[$day] = [
                    'status'  => $status,
                    'details' => [
                        'id'              => $attendance?->id ?? null,
                        'date'            => $dateStr,
                        'leave_weight'    => $leaveDates[$dateStr] ?? null,
                        'clock_in'        => $attendance?->clock_in
                            ? Carbon::parse($attendance->clock_in)->format('h:i A')
                            : null,
                        'clock_out'       => $attendance?->clock_out
                            ? Carbon::parse($attendance->clock_out)->format('h:i A')
                            : null,
                        'break_hours'     => $attendance?->break_hours ?? 0,
                        'total_hours'     => $attendance?->total_hours ?? 0,
                        'overtime_hours'  => $attendance?->overtime_hours ?? 0,
                        // Kept so HR can tell a phone GPS punch from a coarse
                        // network fix (desktops report 1000-5000 m).
                        'clock_in_accuracy_m'  => $attendance?->clock_in_accuracy_m,
                        'clock_out_accuracy_m' => $attendance?->clock_out_accuracy_m,
                        'clock_in_image'  => $attendance?->clock_in_image ?? null,
                        'clock_out_image' => $attendance?->clock_out_image ?? null,
                    ],
                ];
            }

            $employee->attendanceData = $attendanceData;
            $employee->joined_on      = $joinedOn?->toDateString();
        }

        $departments = \App\Models\Department::orderBy('department')->get(['id', 'department as department_name']);

        return view('form.attendance', [
            'employees'     => $employees,
            'selectedMonth' => $selectedMonth,
            'selectedYear'  => $selectedYear,
            'daysInMonth'   => $daysInMonth,
            'employee_name' => $employeeName,
            'departments'   => $departments,
            'departmentId'  => $filterDepartment,
            'includeInactive' => $includeInactive,
        ]);
    }

    // ============================================================
    //  GENERATE QR (AJAX) — cached
    // ============================================================
    public function generateQr(Request $request)
    {
        $request->validate([
            'type' => 'required|in:in,out',
        ]);

        $user = Auth::user();

        try {
            $qr = AttendanceQrCode::freshFor($user->id, $request->type);

            return response()->json([
                'success'    => true,
                'qr_html'    => $qr->getQrHtml(),
                'qr_image'   => $qr->getQrDataUrl(),
                'url'        => $qr->signed_url,
                'type'       => $qr->type,
                'expires_at' => $qr->expires_at->format('H:i A'),
                'user_name'  => $user->name,
                'user_id'    => $user->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Attendance QR generate failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not generate QR code. Please try again.',
            ], 500);
        }
    }

    // ============================================================
    //  DOWNLOAD QR — respects user_id, role-checked
    // ============================================================
    public function downloadQr(Request $request)
    {
        $request->validate([
            'type'    => 'required|in:in,out',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $current = Auth::user();
        $userId  = $request->user_id ? (int) $request->user_id : $current->id;

        if ($userId !== $current->id && !$this->canManageOthers($current)) {
            abort(403);
        }

        $qr = AttendanceQrCode::where('user_id', $userId)
            ->where('type', $request->type)
            ->latest('id')
            ->first();

        if (!$qr || !$qr->qr_base64) {
            return back()->with('error', 'QR code not found. Please generate it first.');
        }

        $decoded = base64_decode($qr->qr_base64);
        $label   = $request->type === 'out' ? 'Check-Out' : 'Check-In';
        $base    = "attendance-{$label}-{$userId}-" . now()->format('Ymd-His');

        if (str_starts_with($decoded, "\x89PNG")) {
            return response($decoded, 200, [
                'Content-Type'        => 'image/png',
                'Content-Disposition' => 'attachment; filename="' . $base . '.png"',
            ]);
        }

        return response($decoded, 200, [
            'Content-Type'        => 'text/html',
            'Content-Disposition' => 'attachment; filename="' . $base . '.html"',
        ]);
    }

    // ============================================================
    //  ADMIN: Generate QR for another employee
    // ============================================================
    public function generateQrForEmployee(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'type'    => 'required|in:in,out',
        ]);

        if (!$this->canManageOthers(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to generate QR for other users.',
            ], 403);
        }

        $targetUser = User::findOrFail($request->user_id);

        try {
            $qr = AttendanceQrCode::freshFor($targetUser->id, $request->type);

            return response()->json([
                'success'    => true,
                'qr_html'    => $qr->getQrHtml(),
                'qr_image'   => $qr->getQrDataUrl(),
                'url'        => $qr->signed_url,
                'type'       => $qr->type,
                'user_name'  => $targetUser->name,
                'user_id'    => $targetUser->id,
                'expires_at' => $qr->expires_at->format('H:i A'),
            ]);
        } catch (\Throwable $e) {
            Log::error('Attendance QR (employee) failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Could not generate QR code.',
            ], 500);
        }
    }

    // ============================================================
    //  Full-page QR views
    // ============================================================
    public function showQrOut()
    {
        $url = URL::temporarySignedRoute(
            'attendance.qrCheckin',
            now()->addMinutes(30),
            ['user_id' => auth()->id(), 'type' => 'out']
        );

        return view('attendance.qr', ['url' => $url, 'type' => 'out']);
    }

    // ============================================================
    //  QR ENTRY — phone lands here after scanning
    // ============================================================
    public function qrCheckin(Request $request)
    {
        $userId = $request->query('user_id');
        // Read from URL, but ensure it's strictly 'in' or 'out'
        $type   = $request->query('type') === 'out' ? 'out' : 'in';

        if (!$userId) {
            abort(400, 'Missing user.');
        }

        session(['qr_user_id' => $userId, 'qr_type' => $type]);

        return view('attendance.phone-checkin', [
            'type'     => $type,
            'qrUserId' => $userId,
        ]);
    }

    // ============================================================
    //  VALIDATE QR — saves attendance. JSON-safe.
    // ============================================================
    public function validateQr(Request $request)
    {
        $data = $request->validate([
            'latitude'  => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy'  => 'required|numeric|min:0',
            'duty_note' => 'nullable|string|max:255',
        ]);

        $userId = session('qr_user_id');
        $type   = session('qr_type', 'in');

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR session. Please scan again.',
            ], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown employee for this QR code.',
            ], 422);
        }

        $attendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', now()->toDateString())
            ->first();

        // Same resolver and same rule as the manual punch card. When checking
        // out, the site the shift started on is tier 1, so a mid-day mapping
        // change cannot strand the employee.
        $expected = $type === 'out' && $attendance && $attendance->clock_in_location_id
            ? Location::find($attendance->clock_in_location_id)
            : $user->attendanceLocation();

        $sites = $this->punchSites();

        if (! $expected && $sites->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your attendance location is not configured. Please contact HR.',
            ], 422);
        }

        $check = $this->geofence->evaluate(
            $expected,
            $sites,
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $data['accuracy']
        );

        if (! $check['allowed']) {
            return response()->json([
                'success'                => false,
                'message'                => $check['message'],
                'distance_m'             => $check['distance_m'],
                'expected_distance_m'    => $check['expected_distance_m'],
                'accuracy_m'             => $check['accuracy_m'],
                'allowed_radius_m'       => $check['allowed_radius_m'],
                'location_name'          => $check['location_name'],
                'expected_location_name' => $expected?->name,
            ], 422);
        }

        $note = trim((string) $request->input('duty_note', ''));

        if ($check['match'] === 'on_duty' && $note === '') {
            return response()->json([
                'success'                => false,
                'duty_required'          => true,
                'match'                  => 'on_duty',
                'message'                => sprintf(
                    'You are at %s, not at your assigned site. Add a short reason so HR can see why.',
                    $check['location_name']
                ),
                'location_name'          => $check['location_name'],
                'expected_location_name' => $expected?->name,
                'expected_distance_m'    => $check['expected_distance_m'],
            ], 422);
        }

        $matched = $check['matched_location'];

        if ($type === 'in') {
            if ($attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already checked in today.',
                ], 400);
            }

            try {
                Attendance::create([
                    'user_id'              => $user->id,
                    'location_id'          => $matched->id,
                    'assigned_location_id' => $expected?->id,
                    'clock_in_location_id' => $matched->id,
                    'clock_in_match'       => $check['match'],
                    'duty_branch_id'       => $check['match'] === 'on_duty' ? $this->dutyBranchId($matched, $user) : null,
                    'duty_note'            => $note !== '' ? $note : null,
                    'date'                 => now()->toDateString(),
                    'clock_in'             => now()->toTimeString(),
                    'clock_in_distance_m'  => (int) round($check['distance_m']),
                    'clock_in_accuracy_m'  => $check['accuracy_m'],
                    'ip_address'           => $request->ip(),
                    'location'             => $data['latitude'] . ',' . $data['longitude'],
                    'status'               => 'present',
                ]);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }

                return response()->json([
                    'success' => false,
                    'message' => 'You have already checked in today.',
                ], 400);
            }

            session()->forget(['qr_user_id', 'qr_type']);

            return response()->json([
                'success'                => true,
                'match'                  => $check['match'],
                'message'                => $check['match'] === 'on_duty'
                    ? sprintf('Checked in - on duty at %s.', $check['location_name'])
                    : 'Checked in successfully!',
                'distance_m'             => $check['distance_m'],
                'expected_distance_m'    => $check['expected_distance_m'],
                'accuracy_m'             => $check['accuracy_m'],
                'allowed_radius_m'       => $check['allowed_radius_m'],
                'location_name'          => $check['location_name'],
                'expected_location_name' => $expected?->name,
            ]);
        }

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'You must check in first.',
            ], 400);
        }

        if ($attendance->clock_out) {
            return response()->json([
                'success' => false,
                'message' => 'You have already checked out today.',
            ], 400);
        }

        $attendance->fill([
            'clock_out'             => now()->toTimeString(),
            'location_id'           => $matched->id,
            'clock_out_location_id' => $matched->id,
            'clock_out_match'       => $check['match'],
            'duty_branch_id'        => $attendance->duty_branch_id
                ?: ($check['match'] === 'on_duty' ? $this->dutyBranchId($matched, $user) : null),
            'duty_note'             => $attendance->duty_note ?: ($note !== '' ? $note : null),
            'clock_out_distance_m'  => (int) round($check['distance_m']),
            'clock_out_accuracy_m'  => $check['accuracy_m'],
        ]);

        $attendance->fill($this->calculator->totals($attendance));
        $attendance->save();

        session()->forget(['qr_user_id', 'qr_type']);

        return response()->json([
            'success'                => true,
            'match'                  => $check['match'],
            'message'                => $check['match'] === 'on_duty'
                ? sprintf('Checked out - on duty at %s.', $check['location_name'])
                : 'Checked out successfully!',
            'distance_m'             => $check['distance_m'],
            'expected_distance_m'    => $check['expected_distance_m'],
            'accuracy_m'             => $check['accuracy_m'],
            'allowed_radius_m'       => $check['allowed_radius_m'],
            'location_name'          => $check['location_name'],
            'expected_location_name' => $expected?->name,
        ]);
    }

    // ============================================================
    //  HELPERS
    // ============================================================

    protected function canManageOthers($user): bool
    {
        return $user->hasAnyRole([
            'Admin',
            'Super Admin',
            'HR',
            'CEO',
            'branch_manager',
            'diputy',
            'Pas Director',
        ]);
    }

    /**
     * Every active site an employee is allowed to punch from.
     * Deactivating a location in Attendance -> Locations removes it here.
     */
    protected function punchSites()
    {
        return Location::query()->active()->orderBy('name')->get();
    }

    /**
     * Branch mapped to the site that actually matched, preferring the
     * employee's own branch when one site serves several of them.
     */
    protected function dutyBranchId(Location $location, ?User $user = null): ?int
    {
        try {
            $branches = $location->branches()->get();

            if ($branches->isEmpty()) {
                return null;
            }

            if ($user && $user->branch_id) {
                $own = $branches->firstWhere('id', $user->branch_id);

                if ($own) {
                    return $own->id;
                }
            }

            return $branches->first()->id;
        } catch (\Throwable $e) {
            Log::warning('Could not resolve duty branch for location ' . $location->id . ': ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Success text shared by the manual card and the QR page, so "on duty"
     * is always spelled out the same way.
     *
     * @param  array<string, mixed>  $check
     */
    protected function punchMessage(string $verb, array $check): string
    {
        if ($check['match'] === 'on_duty') {
            return sprintf(
                '%s - on duty at %s (%s m from your assigned site).',
                $verb,
                $check['location_name'],
                round((float) $check['expected_distance_m'])
            );
        }

        return sprintf(
            '%s at %s - %s m away.',
            $verb,
            $check['location_name'],
            round((float) $check['distance_m'])
        );
    }

    /**
     * Friendly response when HR has not mapped this employee to a geofence.
     */
    protected function locationNotConfigured()
    {
        $message = 'Your attendance location is not configured. Please contact HR.';
        Toastr::error($message, 'Location Missing');

        return back()->withErrors(['location' => $message]);
    }
}

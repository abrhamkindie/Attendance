<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\LeaveApprovalController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// Legacy feature pages breadcrumb back to route('home'); keep it as an alias
// to the dashboard so those links resolve without touching every view.
Route::get('/home', fn() => redirect()->route('dashboard'))
    ->middleware('auth')
    ->name('home');

/*
|--------------------------------------------------------------------------
| QR check-in — PUBLIC (a phone scans these; no login)
|--------------------------------------------------------------------------
*/
Route::get('/attendance/qr-checkin', [AttendanceController::class, 'qrCheckin'])
    ->middleware('signed')
    ->name('attendance.qrCheckin');

Route::post('/attendance/validate-qr', [AttendanceController::class, 'validateQr'])
    ->name('attendance.validateQr');

/*
|--------------------------------------------------------------------------
| Authenticated
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // ============================================
    // ATTENDANCE (every authenticated user)
    // ============================================
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('/attendance/filter', [AttendanceController::class, 'filter'])->name('attendance.filter');
    Route::get('/attendance/page', [AttendanceController::class, 'attendanceIndex'])->name('attendance.page');

    Route::post('/attendance/punch-in', [AttendanceController::class, 'punchIn'])->name('attendance.punchIn');
    Route::post('/attendance/punch-out', [AttendanceController::class, 'punchOut'])->name('attendance.punchOut');
    Route::post('/attendance/break-start', [AttendanceController::class, 'breakStart'])->name('attendance.breakStart');
    Route::post('/attendance/break-end', [AttendanceController::class, 'breakEnd'])->name('attendance.breakEnd');

    // QR self-service (public scan + the user's own check-out page)
    Route::get('/attendance/qr-out', [AttendanceController::class, 'showQrOut'])->name('attendance.qrOut');
    Route::post('/attendance/qr/generate', [AttendanceController::class, 'generateQr'])->name('attendance.qr.generate');
    Route::get('/attendance/qr/download', [AttendanceController::class, 'downloadQr'])->name('attendance.qr.download');
    Route::post('/attendance/qr/employee', [AttendanceController::class, 'generateQrForEmployee'])->name('attendance.qr.employee');

    // An employee may always open their OWN story; the controller answers for
    // everyone else (Admin / Super Admin / HR).
    Route::get('/attendance/reports/user/{user}', [AttendanceReportController::class, 'user'])
        ->name('attendance.reports.user');

    // ============================================
    // ATTENDANCE REPORTS (branch / department roll-ups)
    // ============================================
    Route::middleware('role:Admin|Super Admin|HR')
        ->prefix('attendance/reports')
        ->name('attendance.reports.')
        ->group(function () {
            Route::get('/branch', [AttendanceReportController::class, 'branch'])->name('branch');
            Route::get('/department', [AttendanceReportController::class, 'department'])->name('department');
        });

    // ============================================
    // ATTENDANCE LOCATIONS (geofences) — managers only
    // ============================================
    Route::middleware('role:Admin|Super Admin|HR')
        ->prefix('attendance/locations')
        ->name('attendance.locations.')
        ->group(function () {
            Route::get('/', [LocationController::class, 'index'])->name('index');
            Route::get('/create', [LocationController::class, 'create'])->name('create');
            Route::post('/', [LocationController::class, 'store'])->name('store');
            Route::get('/assign', [LocationController::class, 'assign'])->name('assign');
            Route::post('/assign', [LocationController::class, 'assignBranches'])->name('assign.branches');
            Route::post('/assign-user', [LocationController::class, 'assignUser'])->name('assign.user');
            Route::post('/assign-bulk', [LocationController::class, 'assignBulk'])->name('assign.bulk');
            Route::post('/postings', [LocationController::class, 'storePosting'])->name('postings.store');
            Route::delete('/postings/{posting}', [LocationController::class, 'destroyPosting'])->name('postings.destroy');
            // Expands a short maps.app.goo.gl share link server-side (no API key).
            Route::post('/resolve-link', [LocationController::class, 'resolveLink'])->name('resolve-link');
            Route::get('/{location}/edit', [LocationController::class, 'edit'])->name('edit');
            Route::put('/{location}', [LocationController::class, 'update'])->name('update');
            Route::delete('/{location}', [LocationController::class, 'destroy'])->name('destroy');
        });
});

// ============================================
// USER MANAGEMENT — HR / admins
// ============================================
Route::middleware(['auth', 'role:Admin|Super Admin|HR'])
    ->prefix('users')
    ->name('users.')
    ->group(function () {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserController::class, 'update'])->name('update');
        Route::delete('/{user}', [UserController::class, 'destroy'])->name('destroy');
    });

// ============================================
// DEPARTMENT MANAGEMENT — HR / admins
// ============================================
Route::middleware(['auth', 'role:Admin|Super Admin|HR'])
    ->prefix('departments')
    ->name('departments.')
    ->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::get('/create', [DepartmentController::class, 'create'])->name('create');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::get('/{department}/edit', [DepartmentController::class, 'edit'])->name('edit');
        Route::put('/{department}', [DepartmentController::class, 'update'])->name('update');
        Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

// ============================================
// BRANCH MANAGEMENT — HR / admins
// ============================================
Route::middleware(['auth', 'role:Admin|Super Admin|HR'])
    ->prefix('branches')
    ->name('branches.')
    ->group(function () {
        Route::get('/', [BranchController::class, 'index'])->name('index');
        Route::get('/create', [BranchController::class, 'create'])->name('create');
        Route::post('/', [BranchController::class, 'store'])->name('store');
        Route::get('/{branch}/edit', [BranchController::class, 'edit'])->name('edit');
        Route::put('/{branch}', [BranchController::class, 'update'])->name('update');
        Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy');
    });

// ============================================
// LEAVE — self-service (every authenticated user)
// ============================================
Route::middleware('auth')
    ->prefix('leave')
    ->name('leaves.')
    ->group(function () {
        Route::get('/', [LeaveController::class, 'index'])->name('index');
        Route::get('/create', [LeaveController::class, 'create'])->name('create');
        Route::post('/', [LeaveController::class, 'store'])->name('store');
        Route::get('/{leave}', [LeaveController::class, 'show'])->name('show');
        Route::delete('/{leave}/cancel', [LeaveController::class, 'cancel'])->name('cancel');
    });

// Holidays — read-only calendar for every authenticated user
Route::middleware('auth')
    ->get('/holidays', [HolidayController::class, 'browse'])
    ->name('holidays.browse');

// ============================================
// HOLIDAY MANAGEMENT — HR / admins
// ============================================
Route::middleware(['auth', 'role:Admin|Super Admin|HR'])
    ->prefix('admin/holidays')
    ->name('holidays.')
    ->group(function () {
        Route::get('/', [HolidayController::class, 'index'])->name('index');
        Route::get('/create', [HolidayController::class, 'create'])->name('create');
        Route::post('/', [HolidayController::class, 'store'])->name('store');
        Route::get('/{holiday}/edit', [HolidayController::class, 'edit'])->name('edit');
        Route::put('/{holiday}', [HolidayController::class, 'update'])->name('update');
        Route::delete('/{holiday}', [HolidayController::class, 'destroy'])->name('destroy');
    });

// ============================================
// LEAVE APPROVALS — HR / admins
// ============================================
Route::middleware(['auth', 'role:Admin|Super Admin|HR'])
    ->prefix('admin/leaves')
    ->name('leave-approvals.')
    ->group(function () {
        Route::get('/', [LeaveApprovalController::class, 'index'])->name('index');
        Route::get('/{leave}', [LeaveApprovalController::class, 'show'])->name('show');
        Route::patch('/{leave}/approve', [LeaveApprovalController::class, 'approve'])->name('approve');
        Route::patch('/{leave}/reject', [LeaveApprovalController::class, 'reject'])->name('reject');
    });

require __DIR__ . '/auth.php';

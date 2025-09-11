<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Controllers (nếu có)
use App\Http\Controllers\BranchController;
use App\Http\Controllers\Manager\RoomController;
use App\Http\Controllers\Manager\ClassroomController;
use App\Http\Controllers\Manager\ClassScheduleController;
use App\Http\Controllers\Manager\ClassSessionController;
use App\Http\Controllers\Manager\EnrollmentController;
use App\Http\Controllers\Manager\CourseController;
use App\Http\Controllers\Manager\TimesheetController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Teacher\ScheduleController;
use App\Http\Controllers\Manager\PayrollController;
use App\Http\Controllers\Manager\StudentController;
use App\Http\Controllers\Manager\TeacherController;
use App\Http\Controllers\Manager\InvoiceController;
use App\Http\Controllers\Manager\InvoiceItemController;
use App\Http\Controllers\Manager\PaymentController;
use App\Http\Controllers\Manager\TeachingAssignmentController;
use App\Http\Controllers\Manager\TransferController;
use App\Http\Controllers\Manager\TransferAnalyticsController;
use App\Http\Controllers\Manager\TransferAdvancedController;
use App\Http\Controllers\Manager\TransferAuditController;
use App\Http\Controllers\Manager\SessionSubstitutionController;
use App\Http\Controllers\Manager\SubstitutionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Trang chủ → điều hướng theo role của user
Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    $user = auth()->user();

    if ($user->hasRole('admin')) {
        return redirect()->route('admin.dashboard');
    } elseif ($user->hasRole('manager')) {
        return redirect()->route('manager.dashboard');
    } elseif ($user->hasRole('teacher')) {
        return redirect()->route('teacher.dashboard');
    }

    // Fallback cho user không có role hoặc role không xác định
    return redirect()->route('admin.dashboard');
});

// Auth routes (Breeze/Fortify/Jetstream)
if (file_exists(__DIR__ . '/auth.php')) {
    require __DIR__ . '/auth.php';
}

/*
|--------------------------------------------------------------------------
| Protected routes (đã đăng nhập)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->group(function () {

    /*
    |----------------------------------------------------------------------
    | ADMIN AREA
    |  - URL prefix: /admin/...
    |  - Route names: admin.*
    |  - Quyền mặc định: role:admin
    |  - Gợi ý: Bạn có thể thay 'role:admin' bằng 'permission:...' cho từng route
    |----------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->middleware(['role:admin'])->group(function () {

        // Dashboard
        if (class_exists(\App\Http\Controllers\Admin\DashboardController::class)) {
            Route::get('/dashboard', \App\Http\Controllers\Admin\DashboardController::class)
                ->name('dashboard');
        } else {
            // Fallback Inertia page để dev UI trước
            Route::get('/dashboard', function () {
                return Inertia::render('Admin/Dashboard');
            })->name('dashboard');
        }

        // Branch (CRUD) — nếu muốn permission-based:
        // Route::resource('branches', BranchController::class)->middleware('permission:branches.view');
        Route::resource('branches', BranchController::class);

        // =========================
        // Invoices (Admin version)
        // =========================
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/',            [\App\Http\Controllers\Admin\InvoiceController::class, 'index'])->name('index');
            Route::get('/create',      [\App\Http\Controllers\Admin\InvoiceController::class, 'create'])->name('create');
            Route::post('/',           [\App\Http\Controllers\Admin\InvoiceController::class, 'store'])->name('store');
            Route::get('/{invoice}',   [\App\Http\Controllers\Admin\InvoiceController::class, 'show'])->name('show');
            Route::get('/{invoice}/edit', [\App\Http\Controllers\Admin\InvoiceController::class, 'edit'])->name('edit');
            Route::put('/{invoice}',   [\App\Http\Controllers\Admin\InvoiceController::class, 'update'])->name('update');
            Route::delete('/{invoice}',[\App\Http\Controllers\Admin\InvoiceController::class, 'destroy'])->name('destroy');

            // -------- Invoice Items (nested) --------
            Route::post('/{invoice}/items',                         [\App\Http\Controllers\Admin\InvoiceItemController::class, 'store'])->name('items.store');
            Route::put('/{invoice}/items/{item}',                   [\App\Http\Controllers\Admin\InvoiceItemController::class, 'update'])->name('items.update');
            Route::delete('/{invoice}/items/{item}',                [\App\Http\Controllers\Admin\InvoiceItemController::class, 'destroy'])->name('items.destroy');

            // -------- Payments (nested) --------
            Route::post('/{invoice}/payments',                      [\App\Http\Controllers\Admin\PaymentController::class, 'store'])->name('payments.store');
            Route::delete('/{invoice}/payments/{payment}',          [\App\Http\Controllers\Admin\PaymentController::class, 'destroy'])->name('payments.destroy');
        });

        // Rooms moved to manager section for admin|manager access
        // Classrooms moved to manager section for admin|manager access

        // =========================
        // Attendance (alias to teacher attendance with admin permissions)
        // =========================
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AdminAttendanceController::class, 'index'])->name('index');
            Route::get('/sessions/{session}', [AdminAttendanceController::class, 'show'])->name('show');
            Route::post('/sessions/{session}', [AdminAttendanceController::class, 'store'])->name('store');
        });

        // =========================
        // Holidays (resource routes)
        // =========================
        Route::resource('holidays', App\Http\Controllers\Admin\HolidayController::class);

        // =========================
        // Reports
        // =========================
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/revenue',         \App\Http\Controllers\Admin\Reports\RevenueReportController::class)->name('revenue');
            Route::get('/students-classes', \App\Http\Controllers\Admin\Reports\StudentsClassesReportController::class)->name('students-classes');
            Route::get('/teachers-timesheet', \App\Http\Controllers\Admin\Reports\TeachersTimesheetReportController::class)->name('teachers-timesheet');
            Route::get('/transfers',       \App\Http\Controllers\Admin\Reports\TransfersReportController::class)->name('transfers');
        });

        Route::get('/settings', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Cài đặt',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('settings');
    });

    /*
    |----------------------------------------------------------------------
    | MANAGER AREA
    |  - URL prefix: /manager/...
    |  - Route names: manager.*
    |  - Quyền mặc định: role:manager
    |  - Bạn có thể thay/ghép thêm permission:...
    |----------------------------------------------------------------------
    */
    Route::prefix('manager')->name('manager.')->middleware(['role:admin|manager'])->group(function () {

        // Dashboard
        if (class_exists(\App\Http\Controllers\Manager\DashboardController::class)) {
            Route::get('/dashboard', \App\Http\Controllers\Manager\DashboardController::class)
                ->name('dashboard');
        } else {
            Route::get('/dashboard', fn () => Inertia::render('Manager/Dashboard'))->name('dashboard');
        }

        // Ví dụ placeholders theo tài liệu nghiệp vụ (bổ sung controller sau)
        Route::get('/classes', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Quản lý lớp',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('classes');

        Route::get('/tuition', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Học phí',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('tuition');

        // =========================
        // Invoices
        // =========================
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/',            [InvoiceController::class, 'index'])->name('index');
            Route::get('/create',      [InvoiceController::class, 'create'])->name('create');
            Route::post('/',           [InvoiceController::class, 'store'])->name('store');
            Route::get('/{invoice}',   [InvoiceController::class, 'show'])->name('show');
            Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
            Route::put('/{invoice}',   [InvoiceController::class, 'update'])->name('update');
            Route::delete('/{invoice}',[InvoiceController::class, 'destroy'])->name('destroy');

            // -------- Invoice Items (nested) --------
            Route::post('/{invoice}/items',                         [InvoiceItemController::class, 'store'])->name('items.store');
            Route::put('/{invoice}/items/{item}',                   [InvoiceItemController::class, 'update'])->name('items.update');
            Route::delete('/{invoice}/items/{item}',                [InvoiceItemController::class, 'destroy'])->name('items.destroy');

            // -------- Payments (nested) --------
            Route::post('/{invoice}/payments',                      [PaymentController::class, 'store'])->name('payments.store');
            Route::delete('/{invoice}/payments/{payment}',          [PaymentController::class, 'destroy'])->name('payments.destroy');
        });

        // =========================
        // Attendance (alias to teacher attendance with manager permissions)
        // =========================
        Route::prefix('attendance')->name('attendance.')->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::get('/sessions/{session}', [AttendanceController::class, 'show'])->name('show');
            Route::post('/sessions/{session}', [AttendanceController::class, 'store'])->name('store');
        });

        // =========================
        // Reports
        // =========================
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/students',        \App\Http\Controllers\Manager\Reports\StudentsReportController::class)->name('students');
            Route::get('/classes',         \App\Http\Controllers\Manager\Reports\ClassesReportController::class)->name('classes');
            Route::get('/teachers',        \App\Http\Controllers\Manager\Reports\TeachersReportController::class)->name('teachers');
            Route::get('/finance',         \App\Http\Controllers\Manager\Reports\FinanceReportController::class)->name('finance');
        });
    });

    /*
    |----------------------------------------------------------------------
    | TEACHER AREA
    |  - URL prefix: /teacher/...
    |  - Route names: teacher.*
    |  - Quyền mặc định: role:teacher
    |----------------------------------------------------------------------
    */
    Route::middleware(['auth', 'verified', 'role:teacher'])
    ->prefix('teacher')
    ->name('teacher.')
    ->group(function () {
        // Dashboard
        if (class_exists(DashboardController::class)) {
            Route::get('/dashboard', DashboardController::class)
                ->name('dashboard');
        } else {
            Route::get('/dashboard', fn () => Inertia::render('Teacher/Dashboard'))->name('dashboard');
        }


        // Điểm danh
        Route::get('attendance', [AttendanceController::class, 'index'])
            ->name('attendance.index'); // Danh sách buổi của tôi

        Route::get('attendance/sessions/{session}', [AttendanceController::class, 'show'])
            ->name('attendance.show');   // Mở phiếu điểm danh cho 1 buổi

        Route::post('attendance/sessions/{session}', [AttendanceController::class, 'store'])
            ->name('attendance.store');  // Lưu điểm danh

        // Lịch dạy
        Route::get('schedule', [ScheduleController::class, 'index'])
            ->name('schedule.index'); // Lịch dạy của tôi
    });

    /*
    |----------------------------------------------------------------------
    | Branch Switch (dùng chung Admin/Manager)
    |  - KHÔNG đặt trong prefix để cả 2 vai trò dùng chung
    |  - Có thể thay thế bằng controller thật nếu bạn đã tạo
    |----------------------------------------------------------------------
    */
    if (class_exists(\App\Http\Controllers\BranchSwitchController::class)) {
        Route::post('/switch-branch', \App\Http\Controllers\BranchSwitchController::class)
            ->name('branch.switch');
    } else {
        // Fallback: cập nhật session view_branch_id + flash toast
        Route::post('/switch-branch', function (Request $request) {
            $val = $request->input('branch_id');
            if ($val === null || $val === '' || $val === 'all') {
                session()->forget('view_branch_id');
                session()->flash('success', 'Đang xem: Tất cả chi nhánh');
            } else {
                $id = (int) $val;
                session(['view_branch_id' => $id]);
                session()->flash('success', 'Đã chuyển chi nhánh');
            }
            return back();
        })->name('branch.switch');
    }

    /*
    |----------------------------------------------------------------------
    | Route dùng chung Admin/Manager
    |  - KHÔNG đặt trong prefix để cả 2 vai trò dùng chung
    |----------------------------------------------------------------------
    */
    Route::middleware(['auth', 'role:admin|manager'])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {
        // ROOMS (CRUD) - Shared by Admin and Manager
        Route::resource('rooms', RoomController::class);

        // COURSES (CRUD) - Shared by Admin and Manager
        Route::resource('courses', CourseController::class);

        // CLASSROOMS (CRUD) - Shared by Admin and Manager
        Route::resource('classrooms', ClassroomController::class);

        // Nested resource: schedules thuộc về classroom
        Route::prefix('classrooms/{classroom}')
            ->name('classrooms.')
            ->group(function () {
            Route::resource('schedules', ClassScheduleController::class)
            ->parameters(['schedules' => 'schedule'])   // {schedule} cho model binding
            ->scoped(['schedule' => 'id']);             // scope theo id, hoặc có thể thay bằng 'uuid' nếu bạn dùng

            // Generate sessions (needs classroom parameter for route-model binding)
            Route::post('sessions/generate', [ClassSessionController::class, 'generate'])
                ->name('sessions.generate');
            Route::get('sessions', [ClassSessionController::class, 'index'])
                ->name('sessions.index'); // List buổi theo lớp
            Route::put('sessions/{session}', [ClassSessionController::class, 'update'])
                ->name('sessions.update'); // Cập nhật giờ/room/status, có kiểm tra trùng phòng
            // ✅ NEW: Week View (theo lớp, có filter phòng và tuần)
            Route::get('sessions/week', [ClassSessionController::class, 'week'])
                ->name('sessions.week');
            Route::post('sessions', [ClassSessionController::class, 'store'])
                ->name('sessions.store');
            Route::post('sessions/bulk-room', [ClassSessionController::class, 'bulkAssignRoom'])
                ->name('sessions.bulk-room');
            // Dạy thay (substitutions)
            Route::prefix('sessions/{class_session}')->name('sessions.')->where(['class_session' => '[0-9]+'])->group(function () {
                // Dạy thay (substitutions)
                Route::post('substitutions',               [SessionSubstitutionController::class, 'store'])->name('substitutions.store');
                Route::put('substitutions/{substitution}', [SessionSubstitutionController::class, 'update'])->name('substitutions.update')->where('substitution', '[0-9]+');
                Route::delete('substitutions/{substitution}', [SessionSubstitutionController::class, 'destroy'])->name('substitutions.destroy')->where('substitution', '[0-9]+');
            });

            // ENROLLMENTS (ghi danh)
            Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
            Route::post('enrollments',       [EnrollmentController::class, 'store'])->name('enrollments.store');
            Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
            Route::post('enrollments/bulk', [EnrollmentController::class, 'bulkStore'])->name('enrollments.bulk-store');

            // (tuỳ chọn) API tìm học viên để gợi ý autocomplete
            Route::get('enrollments/search-students', [EnrollmentController::class, 'searchStudents'])
                ->name('enrollments.search-students');
        });

        // SUBSTITUTIONS (dạy thay)
        Route::get('substitutions', [SubstitutionController::class, 'index'])
            ->name('substitutions.index');

        // TIMESHEETS
        Route::get('timesheets', [TimesheetController::class, 'index'])->name('timesheets.index');
        Route::post('timesheets/{id}/approve', [TimesheetController::class, 'approve'])->name('timesheets.approve');
        Route::post('timesheets/bulk-approve', [TimesheetController::class, 'bulkApprove'])->name('timesheets.bulk-approve');

        // PAYROLLS
        Route::prefix('payrolls')->name('payrolls.')->group(function () {
            Route::get('/',            [PayrollController::class, 'index'])->name('index');   // danh sách kỳ
            Route::get('/create',      [PayrollController::class, 'create'])->name('create'); // form tạo kỳ
            Route::post('/',           [PayrollController::class, 'store'])->name('store');   // generate từ timesheet
            Route::get('/{payroll}',   [PayrollController::class, 'show'])->name('show');     // xem chi tiết items

            // hành động trạng thái
            Route::post('/{payroll}/approve', [PayrollController::class, 'approve'])->name('approve');
            Route::post('/{payroll}/lock',    [PayrollController::class, 'lock'])->name('lock');

            // chỉ cho phép xoá khi vẫn là draft
            Route::delete('/{payroll}', [PayrollController::class, 'destroy'])->name('destroy');

            // tuỳ chọn (nếu đã làm)
            Route::post('/{payroll}/lock',   [PayrollController::class, 'lock'])->name('lock');
            Route::get('/{payroll}/export',  [PayrollController::class, 'export'])->name('export');
        });

         // (tuỳ chọn) API gợi ý tìm học viên cho AutoComplete
         Route::get('students/search',         [StudentController::class, 'search'])->name('students.search');

         // STUDENTS
         Route::resource('students', StudentController::class);

        // TEACHERS
        Route::resource('teachers', TeacherController::class);
        Route::get('teachers/search', [TeacherController::class, 'search'])->name('teachers.search');

        // TEACHING ASSIGNMENTS (Phân công dạy)
        Route::prefix('classrooms/{classroom}')->as('classrooms.')->group(function () {
            Route::get('assignments',                [TeachingAssignmentController::class, 'index'])->name('assignments.index');
            Route::get('assignments/create',         [TeachingAssignmentController::class, 'create'])->name('assignments.create');
            Route::post('assignments',               [TeachingAssignmentController::class, 'store'])->name('assignments.store');
            Route::get('assignments/{assignment}/edit', [TeachingAssignmentController::class, 'edit'])->name('assignments.edit');
            Route::put('assignments/{assignment}',   [TeachingAssignmentController::class, 'update'])->name('assignments.update');
            Route::delete('assignments/{assignment}',[TeachingAssignmentController::class, 'destroy'])->name('assignments.destroy');
        });

        // TRANSFERS
        // Analytics routes MUST come before resource routes to avoid conflicts
        Route::get('transfers/analytics/export', [TransferAnalyticsController::class, 'export'])
            ->name('transfers.analytics.export');
        Route::get('transfers/analytics', [TransferAnalyticsController::class, 'index'])
            ->name('transfers.analytics');

        // Advanced Transfer Features (Phase 4)
        Route::prefix('transfers/advanced')->name('transfers.advanced.')->group(function () {
            Route::get('search', [TransferAdvancedController::class, 'search'])
                ->name('search');
            Route::get('history', [TransferAdvancedController::class, 'history'])
                ->name('history');
            Route::get('reports', [TransferAdvancedController::class, 'reports'])
                ->name('reports');
            Route::get('reports/export', [TransferAdvancedController::class, 'exportReports'])
                ->name('reports.export');
        });

        // Transfer History for specific student
        Route::get('students/{student}/transfer-history', [TransferAdvancedController::class, 'studentHistory'])
            ->name('students.transfer-history');

        Route::resource('transfers', TransferController::class);
        Route::post('transfers/revert',   [TransferController::class, 'revert'])->name('transfers.revert');
        Route::post('transfers/retarget', [TransferController::class, 'retarget'])->name('transfers.retarget');

        // Invoice Safety APIs (Priority 3 Enhancement)
        Route::post('transfers/check-revert-safety', [TransferController::class, 'checkRevertSafety'])
            ->name('transfers.check-revert-safety');
        Route::post('transfers/check-retarget-safety', [TransferController::class, 'checkRetargetSafety'])
            ->name('transfers.check-retarget-safety');

        // Alias route for backward compatibility
        Route::post('transfers/invoice-safety/validate', [TransferController::class, 'checkRevertSafety'])
            ->name('transfers.invoice-safety.validate');

        // Audit Routes (Priority 2 Enhancement)
        Route::prefix('transfers')->name('transfers.')->group(function () {
            Route::get('audit/search', [TransferAuditController::class, 'search'])
                ->name('audit.search');
            Route::get('audit/export-search', [TransferAuditController::class, 'exportSearch'])
                ->name('audit.export-search');
            Route::get('{transfer}/audit', [TransferAuditController::class, 'show'])
                ->name('audit.show');
            Route::get('{transfer}/audit/export', [TransferAuditController::class, 'export'])
                ->name('audit.export');
        });

        // Legacy support for student transfer
        Route::post('students/{student}/transfer', [TransferController::class, 'storeForStudent'])
            ->name('students.transfer');         // API gợi ý tìm lớp học cho AutoComplete
         Route::get('classrooms/search',       [ClassroomController::class, 'search'])->name('classrooms.search');
    });
});

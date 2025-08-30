<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Controllers (nếu có)
use App\Http\Controllers\BranchController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassScheduleController;
use App\Http\Controllers\Admin\ClassSessionController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Manager\TimesheetController;
use App\Http\Controllers\Teacher\AttendanceController;
use App\Http\Controllers\Teacher\DashboardController;
use App\Http\Controllers\Manager\PayrollController;
use App\Http\Controllers\Manager\StudentController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\InvoiceItemController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Manager\TeachingAssignmentController;

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

        // Rooms (CRUD) — nếu muốn permission-based:
        // Route::resource('rooms', RoomController::class)->middleware('permission:rooms.view');
        Route::resource('rooms', RoomController::class);

        // Classrooms (CRUD)
        // Ví dụ permission-based chi tiết:
        // Route::resource('classrooms', ClassroomController::class)
        //     ->only(['index','show'])->middleware('permission:classrooms.view');
        // Route::resource('classrooms', ClassroomController::class)
        //     ->only(['create','store','edit','update','destroy'])->middleware('permission:classrooms.manage');
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
            // ENROLLMENTS (ghi danh)
            Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
            Route::post('enrollments',       [EnrollmentController::class, 'store'])->name('enrollments.store');
            Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->name('enrollments.destroy');
            Route::post('enrollments/bulk', [EnrollmentController::class, 'bulkStore'])->name('enrollments.bulk-store');

            // (tuỳ chọn) API tìm học viên để gợi ý autocomplete
            Route::get('enrollments/search-students', [EnrollmentController::class, 'searchStudents'])
                ->name('enrollments.search-students');
        });

        // =========================
        // Course
        // =========================
        Route::resource('courses', CourseController::class);

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

            // -------- Invoice Items (nested) --------
            Route::prefix('/{invoice}')->group(function () {
                Route::post('items', [InvoiceItemController::class, 'store'])
                    ->name('items.store');
                Route::put('items/{item}', [InvoiceItemController::class, 'update'])
                    ->name('items.update');
                Route::delete('items/{item}', [InvoiceItemController::class, 'destroy'])
                    ->name('items.destroy');
            });
        });

        Route::get('/attendance', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Điểm danh',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('attendance');

        Route::get('/reports', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Báo cáo',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('reports');

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
    Route::middleware(['auth', 'verified', 'role:manager'])
    ->prefix('manager')
    ->name('manager.')
    ->group(function () {
    //Route::prefix('manager')->name('manager.')->middleware(['role:manager'])->group(function () {

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

        Route::get('/reports', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Báo cáo',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('reports');
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

         // STUDENTS
         Route::get('students',                [StudentController::class, 'index'])->name('students.index');
         Route::get('students/create',         [StudentController::class, 'create'])->name('students.create');
         Route::post('students',               [StudentController::class, 'store'])->name('students.store');
         Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
         Route::put('students/{student}',      [StudentController::class, 'update'])->name('students.update');
         Route::delete('students/{student}',   [StudentController::class, 'destroy'])->name('students.destroy');

        // TEACHING ASSIGNMENTS (Phân công dạy)
        Route::prefix('classrooms/{classroom}')->as('classrooms.')->group(function () {
            Route::get('assignments',                [TeachingAssignmentController::class, 'index'])->name('assignments.index');
            Route::get('assignments/create',         [TeachingAssignmentController::class, 'create'])->name('assignments.create');
            Route::post('assignments',               [TeachingAssignmentController::class, 'store'])->name('assignments.store');
            Route::get('assignments/{assignment}/edit', [TeachingAssignmentController::class, 'edit'])->name('assignments.edit');
            Route::put('assignments/{assignment}',   [TeachingAssignmentController::class, 'update'])->name('assignments.update');
            Route::delete('assignments/{assignment}',[TeachingAssignmentController::class, 'destroy'])->name('assignments.destroy');
        });

         // (tuỳ chọn) API gợi ý tìm học viên cho AutoComplete
         Route::get('students/search',         [StudentController::class, 'search'])->name('students.search');
    });
});

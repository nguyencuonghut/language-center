<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Controllers (nếu có)
use App\Http\Controllers\BranchController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassScheduleController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Trang chủ → tuỳ ý: tạm chuyển về dashboard admin
Route::get('/', function () {
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
        Route::resource('classrooms.schedules', ClassScheduleController::class)
            ->parameters(['schedules' => 'schedule'])   // {schedule} cho model binding
            ->scoped(['schedule' => 'id']);             // scope theo id, hoặc có thể thay bằng 'uuid' nếu bạn dùng

        // Các menu admin CHƯA làm → placeholder
        Route::get('/students', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Học viên',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('students');

        Route::get('/attendance', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Điểm danh',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('attendance');

        Route::get('/courses', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Khóa học',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('courses');

        Route::get('/billing', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Hóa đơn',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('billing');

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
    Route::prefix('manager')->name('manager.')->middleware(['role:manager'])->group(function () {

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
    Route::prefix('teacher')->name('teacher.')->middleware(['role:teacher'])->group(function () {

        // Dashboard
        if (class_exists(\App\Http\Controllers\Teacher\DashboardController::class)) {
            Route::get('/dashboard', \App\Http\Controllers\Teacher\DashboardController::class)
                ->name('dashboard');
        } else {
            Route::get('/dashboard', fn () => Inertia::render('Teacher/Dashboard'))->name('dashboard');
        }

        // Ví dụ placeholders
        Route::get('/schedule', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Lịch dạy',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('schedule');

        Route::get('/attendance', fn () => Inertia::render('Placeholders/ComingSoon', [
            'title' => 'Điểm danh theo buổi',
            'note'  => 'Tính năng đang phát triển.',
        ]))->name('attendance');
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
});

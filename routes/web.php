<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Trang chủ: chuyển về dashboard admin cho tiện (có thể đổi theo vai trò)
Route::get('/', function () {
    return redirect()->route('admin.dashboard');
});

// Auth routes (Breeze/Fortify/Jetstream)
if (file_exists(__DIR__ . '/auth.php')) {
    require __DIR__ . '/auth.php';
}

// Protected routes
Route::middleware(['auth'])->group(function () {

    // ===========================
    // Admin Dashboard
    // ===========================
    if (class_exists(\App\Http\Controllers\Admin\DashboardController::class)) {
        Route::get('/admin/dashboard', \App\Http\Controllers\Admin\DashboardController::class)
            ->name('admin.dashboard');
    } else {
        // Fallback để dev giao diện trước
        Route::get('/admin/dashboard', function (Request $request) {
            return Inertia::render('Admin/Dashboard', [
                // props mẫu nếu cần
            ]);
        })->name('admin.dashboard');
    }

    // ===========================
    // Manager Dashboard
    // ===========================
    if (class_exists(\App\Http\Controllers\Manager\DashboardController::class)) {
        Route::get('/manager/dashboard', \App\Http\Controllers\Manager\DashboardController::class)
            ->name('manager.dashboard');
    } else {
        Route::get('/manager/dashboard', function () {
            return Inertia::render('Manager/Dashboard');
        })->name('manager.dashboard');
    }

    // ===========================
    // Teacher Dashboard
    // ===========================
    if (class_exists(\App\Http\Controllers\Teacher\DashboardController::class)) {
        Route::get('/teacher/dashboard', \App\Http\Controllers\Teacher\DashboardController::class)
            ->name('teacher.dashboard');
    } else {
        Route::get('/teacher/dashboard', function () {
            return Inertia::render('Teacher/Dashboard');
        })->name('teacher.dashboard');
    }

    // ===========================
    // Branch Switch (Admin/Manager dùng)
    // ===========================
    if (class_exists(\App\Http\Controllers\BranchSwitchController::class)) {
        Route::post('/switch-branch', \App\Http\Controllers\BranchSwitchController::class)
            ->name('branch.switch');
    } else {
        // Fallback: cập nhật session view_branch_id + flash toast
        Route::post('/switch-branch', function (Request $request) {
            $val = $request->input('branch_id');

            if ($val === null || $val === '' || $val === 'all') {
                session()->forget('view_branch_id');
                // Gợi ý để AppLayout hiển thị “Tổng quan toàn hệ thống”
                session()->flash('success', 'Đang xem: Tất cả chi nhánh');
            } else {
                $id = (int) $val;
                session(['view_branch_id' => $id]);
                session()->flash('success', 'Đã chuyển chi nhánh');
            }

            // trở về trang trước, giữ nguyên query hiện tại
            return back();
        })->name('branch.switch');
    }

    // ===========================
    // Rooms CRUD
    // ===========================
    // Cần có App\Http\Controllers\RoomController
    Route::resource('rooms', \App\Http\Controllers\RoomController::class);

    // ===========================
    // (Tuỳ chọn) Trang placeholder cho các menu chưa làm
    // ===========================
    Route::get('/admin/classrooms', fn () => Inertia::render('Placeholders/ComingSoon', [
        'title' => 'Lớp học',
        'note'  => 'Tính năng đang phát triển.',
    ]))->name('admin.classrooms');

    Route::get('/admin/students', fn () => Inertia::render('Placeholders/ComingSoon', [
        'title' => 'Học viên',
        'note'  => 'Tính năng đang phát triển.',
    ]))->name('admin.students');

    Route::get('/admin/attendance', fn () => Inertia::render('Placeholders/ComingSoon', [
        'title' => 'Điểm danh',
        'note'  => 'Tính năng đang phát triển.',
    ]))->name('admin.attendance');

    Route::get('/admin/courses', fn () => Inertia::render('Placeholders/ComingSoon', [
        'title' => 'Khóa học',
        'note'  => 'Tính năng đang phát triển.',
    ]))->name('admin.courses');

    Route::get('/admin/billing', fn () => Inertia::render('Placeholders/ComingSoon', [
        'title' => 'Hóa đơn',
        'note'  => 'Tính năng đang phát triển.',
    ]))->name('admin.billing');

    Route::get('/admin/reports', fn () => Inertia::render('Placeholders/ComingSoon', [
        'title' => 'Báo cáo',
        'note'  => 'Tính năng đang phát triển.',
    ]))->name('admin.reports');

    Route::get('/admin/settings', fn () => Inertia::render('Placeholders/ComingSoon', [
        'title' => 'Cài đặt',
        'note'  => 'Tính năng đang phát triển.',
    ]))->name('admin.settings');
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RevenueReportController;
use App\Http\Controllers\Admin\StudentsClassesReportController;
use App\Http\Controllers\Admin\TeachersTimesheetReportController;
use App\Http\Controllers\Admin\TransfersReportController;
use App\Http\Controllers\Manager\FinanceReportController;
use App\Http\Controllers\Manager\StudentsReportController;
use App\Http\Controllers\Manager\ClassesReportController;
use App\Http\Controllers\Manager\TeachersReportController;

// ===== Admin Reports =====
Route::middleware(['auth', 'role:admin'])->prefix('admin/reports')->name('admin.reports.')->group(function () {
    // Báo cáo doanh thu (toàn hệ thống)
    Route::get('/revenue', [RevenueReportController::class, 'index'])->name('revenue');

    // Báo cáo học viên & lớp học
    Route::get('/students-classes', [StudentsClassesReportController::class, 'index'])->name('students-classes');

    // Báo cáo giáo viên & timesheet
    Route::get('/teachers-timesheet', [TeachersTimesheetReportController::class, 'index'])->name('teachers-timesheet');

    // Báo cáo chuyển lớp
    Route::get('/transfers', [TransfersReportController::class, 'index'])->name('transfers');
});

// ===== Manager Reports =====
Route::middleware(['auth', 'role:manager'])->prefix('manager/reports')->name('manager.reports.')->group(function () {
    // Báo cáo học viên (theo chi nhánh)
    Route::get('/students', [StudentsReportController::class, 'index'])->name('students');

    // Báo cáo lớp học (theo chi nhánh)
    Route::get('/classes', [ClassesReportController::class, 'index'])->name('classes');

    // Báo cáo giáo viên (theo chi nhánh)
    Route::get('/teachers', [TeachersReportController::class, 'index'])->name('teachers');

    // Báo cáo tài chính (theo chi nhánh)
    Route::get('/finance', [FinanceReportController::class, 'index'])->name('finance');
});

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClassroomController extends Controller
{
    /**
     * Hiển thị danh sách lớp mà giáo viên này được phân công (dạy chính hoặc dạy thay).
     */
    public function index(Request $request)
    {
        $teacherId = $request->user()->id;

        // Lấy các lớp mà giáo viên này dạy chính hoặc dạy thay
        $classrooms = Classroom::query()
            ->with(['course', 'branch'])
            ->forTeacher($teacherId)
            ->orderByDesc('start_date')
            ->paginate(15);

        return Inertia::render('Teacher/Classrooms/Index', [
            'classrooms' => $classrooms,
        ]);
    }

    /**
     * Hiển thị chi tiết một lớp học (chỉ khi giáo viên này được phân công).
     */
    public function show(Request $request, Classroom $classroom)
    {
        Gate::authorize('view', $classroom);

        $classroom->load(['course', 'branch', 'teachingAssignments.teacher']);

        return Inertia::render('Teacher/Classrooms/Show', [
            'classroom' => $classroom,
        ]);
    }
}

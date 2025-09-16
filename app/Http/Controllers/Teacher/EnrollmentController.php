<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EnrollmentController extends Controller
{
    /**
     * Danh sách học viên của một lớp (teacher chỉ xem được lớp mình dạy).
     */
    public function index(Request $request, Classroom $classroom)
    {
        Gate::authorize('view', $classroom);

        $enrollments = $classroom->enrollments()
            ->with(['student'])
            ->orderBy('enrolled_at', 'desc')
            ->get();

        return Inertia::render('Teacher/Classrooms/Enrollments', [
            'classroom' => $classroom,
            'enrollments' => $enrollments,
        ]);
    }
}

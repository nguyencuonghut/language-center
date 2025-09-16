<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClassScheduleController extends Controller
{
    /**
     * Danh sách lịch học của một lớp (teacher chỉ xem được lớp mình dạy).
     */
    public function index(Request $request, Classroom $classroom)
    {
        Gate::authorize('view', $classroom);

        $schedules = $classroom->schedules()->orderBy('weekday')->get();

        return Inertia::render('Teacher/Classrooms/Schedules', [
            'classroom' => $classroom,
            'schedules' => $schedules,
        ]);
    }
}

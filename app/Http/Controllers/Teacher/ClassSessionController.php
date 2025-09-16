<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClassSessionController extends Controller
{
    /**
     * Danh sách buổi học của một lớp (teacher chỉ xem được lớp mình dạy).
     */
    public function index(Request $request, Classroom $classroom)
    {
        Gate::authorize('view', $classroom);

        $sessions = $classroom->sessions()
            ->with(['room'])
            ->orderBy('session_no')
            ->get();

        return Inertia::render('Teacher/Classrooms/Sessions', [
            'classroom' => $classroom,
            'sessions' => $sessions,
        ]);
    }
}

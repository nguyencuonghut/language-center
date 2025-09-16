<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Classroom;
use App\Services\HolidayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ClassSessionController extends Controller
{
    public function __construct(private HolidayService $holidayService) {}

    /**
     * Danh sách buổi học của một lớp (teacher chỉ xem được lớp mình dạy).
     */
    public function index(Request $request, Classroom $classroom)
    {
        Gate::authorize('view', $classroom);

        // Load branch để đảm bảo branch_id có giá trị
        $classroom->load('branch');

        $sessions = $classroom->sessions()
            ->with(['room', 'substitution.substituteTeacher'])
            ->orderBy('session_no')
            ->get();

        // Thêm holiday_name cho mỗi session nếu có
        $sessions->transform(function ($session) use ($classroom) {
            $holiday = $this->holidayService->findFor($classroom->id, $classroom->branch_id, $session->date);
            $session->holiday_name = $holiday ? $holiday->name : null;
            return $session;
        });

        return Inertia::render('Teacher/Classrooms/Sessions', [
            'classroom' => $classroom,
            'sessions' => $sessions,
        ]);
    }
}

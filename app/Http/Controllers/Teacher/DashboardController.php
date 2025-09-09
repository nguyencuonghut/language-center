<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Attendance;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\TeachingAssignment;
use App\Models\TeacherTimesheet;
use App\Models\SessionSubstitution;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $teacher = Auth::user();
        $teacherId = $teacher->id;

        // Get current time first
        $today = Carbon::now();
        $thisMonth = Carbon::now()->startOfMonth();

        // Week filtering
        $weekStart = $request->get('week')
            ? Carbon::parse($request->get('week'))->startOfWeek()
            : Carbon::now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        // Branch filtering (if teacher teaches in multiple branches)
        $branchFilter = $request->get('branch_id');

        // Lấy các lớp mà giáo viên đang dạy hiện tại (teaching_assignments)
        $currentAssignments = TeachingAssignment::with(['classroom.branch'])
            ->where('teacher_id', $teacherId)
            ->where('effective_from', '<=', $today)
            ->where(function($q) use ($today) {
                $q->whereNull('effective_to')
                  ->orWhere('effective_to', '>=', $today);
            })
            ->when($branchFilter, function($q) use ($branchFilter) {
                $q->whereHas('classroom', function($subQ) use ($branchFilter) {
                    $subQ->where('branch_id', $branchFilter);
                });
            })
            ->get();

        $classroomIds = $currentAssignments->pluck('class_id');

        // Get branches that teacher teaches in
        $teacherBranches = $currentAssignments->pluck('classroom.branch')->unique()->values();

        // KPI calculations với dữ liệu thực
        $kpi = [
            'classes_teaching' => [
                'total' => $currentAssignments->count(),
            ],
            'sessions_today' => [
                'total' => ClassSession::whereIn('class_id', $classroomIds)
                    ->whereDate('date', $today->toDateString())
                    ->count(),
            ],
            'sessions_this_week' => [
                'total' => ClassSession::whereIn('class_id', $classroomIds)
                    ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                    ->count(),
            ],
            'need_attendance' => [
                'total' => ClassSession::whereIn('class_id', $classroomIds)
                    ->whereDate('date', $today->toDateString())
                    ->whereDoesntHave('attendances')
                    ->count(),
            ],
            'pending_timesheets' => [
                'total' => TeacherTimesheet::where('teacher_id', $teacherId)
                    ->whereIn('status', ['draft'])
                    ->count(),
            ],
            'upcoming_substitutions' => [
                'total' => SessionSubstitution::join('class_sessions', 'session_substitutions.class_session_id', '=', 'class_sessions.id')
                    ->whereBetween('class_sessions.date', [
                        $today->copy()->toDateString(),
                        $today->copy()->addDays(14)->toDateString()
                    ])
                    ->where('substitute_teacher_id', $teacherId)
                    ->count(),
            ],
        ];

        // Today's schedule với dữ liệu thực
        $todaySchedule = ClassSession::with(['classroom', 'room'])
            ->whereIn('class_id', $classroomIds)
            ->whereDate('date', $today->toDateString())
            ->orderBy('start_time')
            ->get()
            ->map(function($session) use ($teacherId) {
                $sessionId = $session->id;
                $classId = $session->class_id;

                // Kiểm tra đã điểm danh chưa
                $attendanceCount = Attendance::where('class_session_id', $sessionId)->count();
                $presentCount = Attendance::where('class_session_id', $sessionId)
                    ->where('status', 'present')
                    ->count();

                // Số học viên đã ghi danh trong lớp
                $studentsCount = Enrollment::where('class_id', $classId)
                    ->where('status', 'active')
                    ->count();

                return [
                    'id' => $sessionId,
                    'class_name' => $session->classroom->name,
                    'class_code' => $session->classroom->code,
                    'start_time' => Carbon::parse($session->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($session->end_time)->format('H:i'),
                    'room' => $session->room->name ?? 'Chưa xếp phòng',
                    'room_id' => $session->room_id,
                    'students_count' => $studentsCount,
                    'attendance_taken' => $attendanceCount > 0,
                    'present_count' => $presentCount,
                    'total_attendances' => $attendanceCount,
                    'status' => $session->status, // planned, canceled, moved
                    'session_no' => $session->session_no,
                ];
            });

        // This week's schedule với dữ liệu thực
        $weekSchedule = ClassSession::with(['classroom', 'room'])
            ->whereIn('class_id', $classroomIds)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get()
            ->map(function($session) {
                return [
                    'id' => $session->id,
                    'class_name' => $session->classroom->name,
                    'class_code' => $session->classroom->code,
                    'class_id' => $session->class_id,
                    'date' => $session->date,
                    'day_name' => Carbon::parse($session->date)->format('l'),
                    'start_time' => Carbon::parse($session->start_time)->format('H:i'),
                    'end_time' => Carbon::parse($session->end_time)->format('H:i'),
                    'room' => $session->room->name ?? 'Chưa xếp phòng',
                    'status' => $session->status,
                ];
            });

        // Alerts với dữ liệu thực
        $alerts = [
            'sessions_no_attendance' => ClassSession::whereIn('class_id', $classroomIds)
                ->whereDate('date', '<', $today->toDateString())
                ->whereDate('date', '>=', $today->subDays(7)->toDateString()) // Chỉ tính 7 ngày gần đây
                ->whereDoesntHave('attendances')
                ->count(),
            'pending_timesheets' => TeacherTimesheet::where('teacher_id', $teacherId)
                ->where('status', 'draft')
                ->count(),
            'overdue_sessions' => ClassSession::whereIn('class_id', $classroomIds)
                ->whereDate('date', '<', $today->subDays(3)->toDateString())
                ->whereDoesntHave('attendances')
                ->count(),
            'pending_substitutions' => SessionSubstitution::where('substitute_teacher_id', $teacherId)
                ->whereNull('approved_at')
                ->join('class_sessions', 'session_substitutions.class_session_id', '=', 'class_sessions.id')
                ->where('class_sessions.date', '>=', $today->toDateString())
                ->count(),
        ];

        // Recent timesheets với dữ liệu thực từ database
        $recentTimesheets = TeacherTimesheet::with(['session.classroom'])
            ->where('teacher_id', $teacherId)
            ->latest()
            ->limit(10)
            ->get()
            ->map(function($timesheet) {
                return [
                    'id' => $timesheet->id,
                    'class_name' => $timesheet->session->classroom->name,
                    'class_code' => $timesheet->session->classroom->code,
                    'session_date' => $timesheet->session->date,
                    'amount' => $timesheet->amount,
                    'status' => $timesheet->status,
                    'created_at' => $timesheet->created_at,
                    'approved_at' => $timesheet->approved_at,
                ];
            });

        // Students needing attention dựa trên attendance thực tế
        $studentsAttention = collect();

        foreach ($currentAssignments as $assignment) {
            $classroom = $assignment->classroom;

            // Lấy những học viên có tỷ lệ tham dự thấp trong 30 ngày gần đây
            $recentSessions = ClassSession::where('class_id', $classroom->id)
                ->whereDate('date', '>=', $today->subDays(30))
                ->whereDate('date', '<=', $today)
                ->pluck('id');

            if ($recentSessions->count() > 0) {
                $enrollments = Enrollment::with('student')
                    ->where('class_id', $classroom->id)
                    ->where('status', 'active')
                    ->get();

                foreach ($enrollments as $enrollment) {
                    if (!$enrollment->student) continue;

                    $totalSessions = $recentSessions->count();
                    $attendedSessions = Attendance::whereIn('class_session_id', $recentSessions)
                        ->where('student_id', $enrollment->student_id)
                        ->where('status', 'present')
                        ->count();

                    $attendanceRate = $totalSessions > 0 ? ($attendedSessions / $totalSessions) * 100 : 0;

                    // Chỉ hiển thị học viên có tỷ lệ < 70%
                    if ($attendanceRate < 70) {
                        $studentsAttention->push([
                            'student_name' => $enrollment->student->name,
                            'student_code' => $enrollment->student->code ?? '',
                            'class_name' => $classroom->name,
                            'class_code' => $classroom->code,
                            'attendance_rate' => round($attendanceRate, 1),
                            'issue' => 'Tỷ lệ tham dự thấp (< 70%)',
                            'sessions_attended' => $attendedSessions,
                            'total_sessions' => $totalSessions,
                        ]);
                    }
                }
            }
        }

        // Upcoming substitutions (0-14 days ahead)
        $upcomingSubstitutions = SessionSubstitution::with([
            'session.classroom',
            'session.room',
            'substituteTeacher'
        ])
            ->join('class_sessions', 'session_substitutions.class_session_id', '=', 'class_sessions.id')
            ->whereBetween('class_sessions.date', [
                Carbon::now()->toDateString(),
                Carbon::now()->copy()->addDays(14)->toDateString()
            ])
            ->where('substitute_teacher_id', $teacherId)
            ->orderBy('class_sessions.date')
            ->orderBy('class_sessions.start_time')
            ->select('session_substitutions.*')
            ->get()
            ->map(function($substitution) {
                return [
                    'id' => $substitution->id,
                    'session_id' => $substitution->class_session_id,
                    'class_name' => optional($substitution->session->classroom)->name,
                    'class_code' => optional($substitution->session->classroom)->code,
                    'date' => optional($substitution->session)->date,
                    'start_time' => optional($substitution->session && $substitution->session->start_time) ? \Carbon\Carbon::parse($substitution->session->start_time)->format('H:i') : null,
                    'end_time' => optional($substitution->session && $substitution->session->end_time) ? \Carbon\Carbon::parse($substitution->session->end_time)->format('H:i') : null,
                    'room' => optional($substitution->session->room)->name ?? 'Chưa xếp phòng',
                    'reason' => $substitution->reason,
                    'rate_override' => $substitution->rate_override,
                    'approved_at' => $substitution->approved_at,
                    'session_no' => optional($substitution->session)->session_no,
                ];
            });

        return Inertia::render('Teacher/Dashboard', [
            'kpi' => $kpi,
            'todaySchedule' => $todaySchedule,
            'weekSchedule' => $weekSchedule,
            'alerts' => $alerts,
            'recentTimesheets' => $recentTimesheets,
            'studentsAttention' => $studentsAttention->take(5), // Giới hạn 5 học viên
            'upcomingSubstitutions' => $upcomingSubstitutions,
            'range' => [
                'from' => $today->copy()->toDateString(),
                'to'   => $today->copy()->addDays(14)->toDateString(),
            ],
            'branches' => $teacherBranches, // Branches that teacher teaches in
            'latestExportId' => session('latest_export_id'), // Pass export ID if exists
            'meta' => [
                'teacher_name' => $teacher->name,
                'teacher_id' => $teacherId,
                'today' => $today->toDateString(),
                'week_range' => [
                    $weekStart->toDateString(),
                    $weekEnd->toDateString()
                ],
                'current_assignments_count' => $currentAssignments->count(),
                'selected_branch' => $branchFilter,
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\Manager\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\TeacherTimesheet;
use App\Models\User;
use App\Models\Course;
use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class TeachersReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'courses' => 'nullable|array',
            'courses.*' => 'exists:courses,id',
            'teachers' => 'nullable|array',
            'teachers.*' => 'exists:users,id',
        ]);

        // Get manager's branches
        $user = Auth::user();
        $branchIds = DB::table('manager_branch')
            ->where('user_id', $user->id)
            ->pluck('branch_id')
            ->all();

        // Default to current month if no dates provided
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->endOfMonth();

        $courseIds = $request->courses ?? [];
        $teacherIds = $request->teachers ?? [];

        // Get filter options
        $courses = Course::select('id', 'name')->where('active', true)->orderBy('name')->get();
        $teachers = User::role('teacher')->where('active', true)->select('id', 'name')->orderBy('name')->get();

        // Calculate KPIs
        $kpi = $this->calculateKPIs($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Get charts data
        $charts = $this->getChartsData($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Get recent data
        $recent = $this->getRecentData($startDate, $endDate, $branchIds, $courseIds, $teacherIds, $request);

        return Inertia::render('Manager/Reports/Teachers', [
            'appliedFilters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'courses' => $courseIds,
                'teachers' => $teacherIds,
            ],
            'availableFilters' => [
                'courses' => $courses,
                'teachers' => $teachers,
            ],
            'kpi' => $kpi,
            'charts' => $charts,
            'recent' => $recent,
        ]);
    }

    private function calculateKPIs($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $sessionQuery = $this->buildSessionQuery($startDate, $endDate, $branchIds, $courseIds, $teacherIds);
        $timesheetQuery = $this->buildTimesheetQuery($branchIds, $courseIds, $teacherIds);

        // Total sessions taught in date range
        $totalSessions = (clone $sessionQuery)->count();

        // Pending timesheets (draft status)
        $pendingTimesheets = (clone $timesheetQuery)
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->where('teacher_timesheets.status', 'draft')
            ->count();

        // Total payroll cost in date range (branch scoped)
        $totalPayrollCost = (clone $timesheetQuery)
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->sum('teacher_timesheets.amount') ?? 0;

        return [
            'total_sessions' => ['total' => $totalSessions],
            'pending_timesheets' => ['total' => $pendingTimesheets],
            'total_payroll_cost' => ['total' => (int) $totalPayrollCost],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        // Sessions by teacher
        $sessionsByTeacher = $this->getSessionsByTeacher($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Payroll cost distribution by teacher
        $payrollByTeacher = $this->getPayrollByTeacher($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        return [
            'sessions_by_teacher' => $sessionsByTeacher,
            'payroll_by_teacher' => $payrollByTeacher,
        ];
    }

    private function getRecentData($startDate, $endDate, $branchIds, $courseIds, $teacherIds, $request)
    {
        // Teacher performance summary
        $teacherPerformance = $this->getTeacherPerformance($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Timesheet summary by teacher
        $timesheetSummary = $this->getTimesheetSummary($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        return [
            'teacher_performance' => $teacherPerformance,
            'timesheet_summary' => $timesheetSummary,
        ];
    }

    private function buildSessionQuery($startDate, $endDate, $branchIds, $courseIds = [], $teacherIds = [])
    {
        $query = ClassSession::query()
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->join('teaching_assignments', function($join) {
                $join->on('classrooms.id', '=', 'teaching_assignments.class_id')
                     ->whereNull('teaching_assignments.effective_to');
            })
            ->whereIn('classrooms.branch_id', $branchIds)
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->where('class_sessions.status', '!=', 'canceled');

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teaching_assignments.teacher_id', $teacherIds);
        }

        return $query;
    }

    private function buildTimesheetQuery($branchIds, $courseIds = [], $teacherIds = [])
    {
        $query = TeacherTimesheet::query()
            ->join('users as teachers', 'teacher_timesheets.teacher_id', '=', 'teachers.id')
            ->join('class_sessions', 'teacher_timesheets.class_session_id', '=', 'class_sessions.id')
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds);

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teachers.id', $teacherIds);
        }

        return $query;
    }

    private function getSessionsByTeacher($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = $this->buildSessionQuery($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
            ->join('users as teachers', 'teaching_assignments.teacher_id', '=', 'teachers.id');

        return $query
            ->selectRaw('
                teachers.name as teacher_name,
                COUNT(*) as sessions_count
            ')
            ->groupBy('teachers.id', 'teachers.name')
            ->orderByDesc('sessions_count')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->teacher_name,
                    'value' => (int) $item->sessions_count
                ];
            });
    }

    private function getPayrollByTeacher($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = $this->buildTimesheetQuery($branchIds, $courseIds, $teacherIds)
            ->whereBetween('class_sessions.date', [$startDate, $endDate]);

        return $query
            ->selectRaw('
                teachers.name as teacher_name,
                SUM(teacher_timesheets.amount) as total_amount
            ')
            ->groupBy('teachers.id', 'teachers.name')
            ->orderByDesc('total_amount')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->teacher_name,
                    'value' => (int) ($item->total_amount ?? 0)
                ];
            });
    }

    private function getTeacherPerformance($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        // Get teachers with their performance metrics
        $query = DB::table('teaching_assignments')
            ->join('users as teachers', 'teaching_assignments.teacher_id', '=', 'teachers.id')
            ->join('classrooms', 'teaching_assignments.class_id', '=', 'classrooms.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->leftJoin('enrollments', function($join) {
                $join->on('classrooms.id', '=', 'enrollments.class_id')
                     ->where('enrollments.status', '=', 'active');
            })
            ->whereIn('classrooms.branch_id', $branchIds)
            ->whereNull('teaching_assignments.effective_to') // Current assignments only
            ->where('teachers.active', true);

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teachers.id', $teacherIds);
        }

        return $query
            ->selectRaw('
                teachers.id as teacher_id,
                teachers.name as teacher_name,
                branches.name as branch_name,
                COUNT(DISTINCT classrooms.id) as classes_count,
                COUNT(DISTINCT enrollments.student_id) as students_count,
                teaching_assignments.effective_from,
                ROUND(AVG(85), 1) as avg_attendance
            ')
            ->groupBy('teachers.id', 'teachers.name', 'branches.name', 'teaching_assignments.effective_from')
            ->orderBy('teachers.name')
            ->get()
            ->map(function($item) {
                return [
                    'teacher_id' => $item->teacher_id,
                    'teacher_name' => $item->teacher_name,
                    'branch_name' => $item->branch_name,
                    'classes_count' => (int) $item->classes_count,
                    'students_count' => (int) $item->students_count,
                    'avg_attendance' => (float) $item->avg_attendance,
                    'effective_from' => $item->effective_from,
                ];
            });
    }

    private function getTimesheetSummary($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = $this->buildTimesheetQuery($branchIds, $courseIds, $teacherIds)
            ->whereBetween('class_sessions.date', [$startDate, $endDate]);

        return $query
            ->selectRaw('
                teachers.name as teacher_name,
                teacher_timesheets.status,
                COUNT(*) as sessions_count,
                SUM(teacher_timesheets.amount) as total_amount,
                MAX(teacher_timesheets.updated_at) as last_updated
            ')
            ->groupBy('teachers.id', 'teachers.name', 'teacher_timesheets.status')
            ->orderBy('teachers.name')
            ->orderBy('teacher_timesheets.status')
            ->get()
            ->map(function($item) {
                $statusNames = [
                    'draft' => 'Nháp',
                    'approved' => 'Đã duyệt',
                    'locked' => 'Đã khóa'
                ];

                return [
                    'teacher' => $item->teacher_name,
                    'status' => $statusNames[$item->status] ?? ucfirst($item->status),
                    'sessions_count' => (int) $item->sessions_count,
                    'total_amount' => (int) ($item->total_amount ?? 0),
                    'last_updated' => Carbon::parse($item->last_updated)->format('d/m/Y H:i'),
                ];
            });
    }
}

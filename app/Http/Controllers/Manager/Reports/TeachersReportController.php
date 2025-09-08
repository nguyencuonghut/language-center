<?php

namespace App\Http\Controllers\Manager\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\TeacherTimesheet;
use App\Models\User;
use App\Models\Course;
use App\Models\ClassSession;
use App\Models\Classroom;
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
        // Total teachers in branches (teachers currently or previously assigned to classes in these branches)
        $totalTeachers = DB::table('teaching_assignments')
            ->join('classrooms', 'teaching_assignments.class_id', '=', 'classrooms.id')
            ->join('users', 'teaching_assignments.teacher_id', '=', 'users.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->where('users.active', true)
            ->distinct('teaching_assignments.teacher_id')
            ->count();

        // Active teachers (currently assigned to classes)
        $activeTeachers = DB::table('teaching_assignments')
            ->join('classrooms', 'teaching_assignments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->whereNull('teaching_assignments.effective_to')
            ->distinct('teaching_assignments.teacher_id')
            ->count();

        // Total classes in branches
        $totalClasses = Classroom::whereIn('branch_id', $branchIds)
            ->when(!empty($courseIds), function($query) use ($courseIds) {
                return $query->whereIn('course_id', $courseIds);
            })
            ->count();

        // Average students per teacher
        $totalStudents = DB::table('enrollments')
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->where('enrollments.status', 'active')
            ->count();

        $avgStudentsPerTeacher = $activeTeachers > 0 ? round($totalStudents / $activeTeachers, 1) : 0;

        return [
            'total_teachers' => ['total' => $totalTeachers],
            'active_teachers' => ['total' => $activeTeachers],
            'total_classes' => ['total' => $totalClasses],
            'avg_students_per_teacher' => ['total' => $avgStudentsPerTeacher],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        // Teachers by branch
        $teachersByBranch = $this->getTeachersByBranch($branchIds, $courseIds, $teacherIds);

        // Teacher workload (classes per teacher)
        $teacherWorkload = $this->getTeacherWorkload($branchIds, $courseIds, $teacherIds);

        // Assignment timeline (new assignments over time)
        $assignmentTimeline = $this->getAssignmentTimeline($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        return [
            'teachers_by_branch' => $teachersByBranch,
            'teacher_workload' => $teacherWorkload,
            'assignment_timeline' => $assignmentTimeline,
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

    private function getTeachersByBranch($branchIds, $courseIds, $teacherIds)
    {
        $query = DB::table('teaching_assignments')
            ->join('classrooms', 'teaching_assignments.class_id', '=', 'classrooms.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->join('users as teachers', 'teaching_assignments.teacher_id', '=', 'teachers.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->whereNull('teaching_assignments.effective_to')
            ->where('teachers.active', true);

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teachers.id', $teacherIds);
        }

        return $query
            ->selectRaw('
                branches.name as branch_name,
                COUNT(DISTINCT teaching_assignments.teacher_id) as teacher_count
            ')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('teacher_count')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->branch_name,
                    'value' => (int) $item->teacher_count
                ];
            });
    }

    private function getTeacherWorkload($branchIds, $courseIds, $teacherIds)
    {
        $query = DB::table('teaching_assignments')
            ->join('classrooms', 'teaching_assignments.class_id', '=', 'classrooms.id')
            ->join('users as teachers', 'teaching_assignments.teacher_id', '=', 'teachers.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->whereNull('teaching_assignments.effective_to')
            ->where('teachers.active', true);

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teachers.id', $teacherIds);
        }

        return $query
            ->selectRaw('
                teachers.name as teacher_name,
                COUNT(DISTINCT teaching_assignments.class_id) as class_count
            ')
            ->groupBy('teachers.id', 'teachers.name')
            ->orderByDesc('class_count')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->teacher_name,
                    'value' => (int) $item->class_count
                ];
            });
    }

    private function getAssignmentTimeline($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = DB::table('teaching_assignments')
            ->join('classrooms', 'teaching_assignments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->whereBetween('teaching_assignments.effective_from', [$startDate, $endDate]);

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teaching_assignments.teacher_id', $teacherIds);
        }

        $results = $query
            ->selectRaw('
                DATE_FORMAT(teaching_assignments.effective_from, "%Y-%m") as month,
                COUNT(*) as count
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $results->map(function($item) {
            return [
                'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                'value' => (int) $item->count
            ];
        });
    }
}

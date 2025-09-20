<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\TeacherTimesheet;
use App\Models\User;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TeachersTimesheetReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'branches' => 'nullable|array',
            'branches.*' => 'exists:branches,id',
            'courses' => 'nullable|array',
            'courses.*' => 'exists:courses,id',
            'teachers' => 'nullable|array',
            'teachers.*' => 'exists:users,id',
        ]);

        // Default to current month if no dates provided
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->endOfMonth();

        $branchIds = $request->branches ?? [];
        $courseIds = $request->courses ?? [];
        $teacherIds = $request->teachers ?? [];

        // Get filter options
        $branches = Branch::select('id', 'name')->orderBy('name')->get();
        $courses = Course::select('id', 'name')->where('active', true)->orderBy('name')->get();
        $teachers = User::role('teacher')->where('active', true)->select('id', 'name')->orderBy('name')->get();

        // Calculate KPIs
        $kpi = $this->calculateKPIs($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Get charts data
        $charts = $this->getChartsData($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Get detailed tables data
        $tables = $this->getTablesData($startDate, $endDate, $branchIds, $courseIds, $teacherIds, $request);

        return Inertia::render('Admin/Reports/TeachersTimesheet', [
            'appliedFilters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'branch_ids' => $branchIds,
                'course_ids' => $courseIds,
                'teacher_ids' => $teacherIds,
            ],
            'availableFilters' => [
                'branches' => Branch::where('active', true)->get(['id', 'name']),
                'courses' => Course::where('active', true)->get(['id', 'name']),
                'teachers' => User::role('teacher')->where('active', true)->get(['id', 'name']),
            ],
            'kpi' => $kpi,
            'charts' => $charts,
            'recent' => [
                'teacher_summary' => $this->getTimesheetSummary($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
            ],
        ]);
    }

    private function calculateKPIs($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $timesheetQuery = $this->buildTimesheetQuery($branchIds, $courseIds, $teacherIds);
        $sessionQuery = $this->buildSessionQuery($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Total sessions taught in date range
        $totalSessions = (clone $sessionQuery)->count();

        // Total payroll cost in date range
        $totalPayrollCost = (clone $timesheetQuery)
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->sum('teacher_timesheets.amount') ?? 0;

        // Timesheet status counts
        $timesheetStats = (clone $timesheetQuery)
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN teacher_timesheets.status = "draft" THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN teacher_timesheets.status = "approved" THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN teacher_timesheets.status = "locked" THEN 1 ELSE 0 END) as locked_count
            ')
            ->first();

        return [
            'total_sessions' => ['total' => $totalSessions],
            'total_payroll_cost' => ['total' => (int) $totalPayrollCost],
            'timesheet_draft' => ['total' => (int) $timesheetStats->draft_count],
            'timesheet_approved' => ['total' => (int) $timesheetStats->approved_count],
            'timesheet_locked' => ['total' => (int) $timesheetStats->locked_count],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        // Sessions by teacher
        $sessionsByTeacher = $this->getSessionsByTeacher($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Monthly payroll cost
        $monthlyPayrollCost = $this->getMonthlyPayrollCost($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        // Timesheet status funnel
        $timesheetStatusFunnel = $this->getTimesheetStatusFunnel($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        return [
            'sessions_by_teacher' => $sessionsByTeacher,
            'monthly_payroll_cost' => $monthlyPayrollCost,
            'timesheet_status_funnel' => $timesheetStatusFunnel,
        ];
    }

    private function getTablesData($startDate, $endDate, $branchIds, $courseIds, $teacherIds, $request)
    {
        // Timesheet summary by period
        $timesheetSummary = $this->getTimesheetSummary($startDate, $endDate, $branchIds, $courseIds, $teacherIds);

        return [
            'timesheet_summary' => $timesheetSummary,
        ];
    }

    private function buildTimesheetQuery($branchIds = [], $courseIds = [], $teacherIds = [])
    {
        $query = TeacherTimesheet::query()
            ->join('teachers as teachers', 'teacher_timesheets.teacher_id', '=', 'teachers.id')
            ->join('class_sessions', 'teacher_timesheets.class_session_id', '=', 'class_sessions.id')
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id');

        if (!empty($branchIds)) {
            $query->whereIn('branches.id', $branchIds);
        }

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teachers.id', $teacherIds);
        }

        return $query;
    }

    private function buildSessionQuery($startDate, $endDate, $branchIds = [], $courseIds = [], $teacherIds = [])
    {
        $query = ClassSession::query()
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->join('teaching_assignments', function($join) {
                $join->on('classrooms.id', '=', 'teaching_assignments.class_id')
                     ->whereNull('teaching_assignments.effective_to');
            })
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->where('class_sessions.status', '!=', 'canceled');

        if (!empty($branchIds)) {
            $query->whereIn('branches.id', $branchIds);
        }

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        if (!empty($teacherIds)) {
            $query->whereIn('teaching_assignments.teacher_id', $teacherIds);
        }

        return $query;
    }

    private function getSessionsByTeacher($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = $this->buildSessionQuery($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
            ->join('teachers as teachers', 'teaching_assignments.teacher_id', '=', 'teachers.id');

        return $query
            ->selectRaw('
                teachers.name as teacher_name,
                COUNT(*) as sessions_count
            ')
            ->groupBy('teachers.id', 'teachers.name')
            ->orderByDesc('sessions_count')
            ->limit(15)
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->teacher_name,
                    'value' => (int) $item->sessions_count
                ];
            });
    }

    private function getMonthlyPayrollCost($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = $this->buildTimesheetQuery($branchIds, $courseIds, $teacherIds)
            ->whereBetween('class_sessions.date', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                DATE_FORMAT(class_sessions.date, "%Y-%m") as month,
                SUM(teacher_timesheets.amount) as total_cost
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $results->map(function($item) {
            return [
                'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                'value' => (int) ($item->total_cost ?? 0)
            ];
        });
    }

    private function getTimesheetStatusFunnel($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = $this->buildTimesheetQuery($branchIds, $courseIds, $teacherIds)
            ->whereBetween('class_sessions.date', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                teacher_timesheets.status,
                COUNT(*) as count
            ')
            ->groupBy('teacher_timesheets.status')
            ->get();

        return $results->map(function($item) {
            return [
                'name' => ucfirst($item->status),
                'value' => (int) $item->count
            ];
        });
    }

    private function getTimesheetSummary($startDate, $endDate, $branchIds, $courseIds, $teacherIds)
    {
        $query = $this->buildTimesheetQuery($branchIds, $courseIds, $teacherIds)
            ->whereBetween('class_sessions.date', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                DATE_FORMAT(class_sessions.date, "%Y-%m") as period,
                teachers.name as teacher_name,
                COUNT(*) as sessions_count,
                SUM(teacher_timesheets.amount) as total_amount,
                teacher_timesheets.status,
                MAX(teacher_timesheets.updated_at) as last_updated
            ')
            ->groupBy('period', 'teachers.id', 'teachers.name', 'teacher_timesheets.status')
            ->orderBy('period', 'desc')
            ->orderBy('teachers.name')
            ->get();

        return $results->map(function($item) {
            return [
                'period' => Carbon::createFromFormat('Y-m', $item->period)->format('M Y'),
                'teacher' => $item->teacher_name,
                'sessions_count' => (int) $item->sessions_count,
                'total_amount' => (int) ($item->total_amount ?? 0),
                'status' => ucfirst($item->status),
                'last_updated' => Carbon::parse($item->last_updated)->format('d/m/Y H:i'),
            ];
        });
    }
}

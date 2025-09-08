<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class StudentsClassesReportController extends Controller
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
        ]);

        // Default to current month if no dates provided
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->endOfMonth();

        $branchIds = $request->branches ?? [];
        $courseIds = $request->courses ?? [];

        // Get filter options
        $branches = Branch::select('id', 'name')->orderBy('name')->get();
        $courses = Course::select('id', 'name')->where('active', true)->orderBy('name')->get();

        // Calculate KPIs
        $kpi = $this->calculateKPIs($startDate, $endDate, $branchIds, $courseIds);

        // Get charts data
        $charts = $this->getChartsData($startDate, $endDate, $branchIds, $courseIds);

        // Get detailed tables data
        $tables = $this->getTablesData($startDate, $endDate, $branchIds, $courseIds, $request);

        return Inertia::render('Admin/Reports/StudentsClasses', [
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'branches' => $branchIds,
                'courses' => $courseIds,
            ],
            'filter_options' => [
                'branches' => $branches,
                'courses' => $courses,
            ],
            'kpi' => $kpi,
            'charts' => $charts,
            'tables' => $tables,
        ]);
    }

    private function calculateKPIs($startDate, $endDate, $branchIds, $courseIds)
    {
        $classQuery = $this->buildClassQuery($branchIds, $courseIds);
        $enrollmentQuery = $this->buildEnrollmentQuery($branchIds, $courseIds);

        // Classes open/closed
        $openClasses = (clone $classQuery)->whereIn('status', ['open', 'active'])->count();
        $closedClasses = (clone $classQuery)->whereIn('status', ['closed', 'completed'])->count();

        // New enrollments in date range
        $newEnrollments = (clone $enrollmentQuery)
            ->whereBetween('enrollments.enrolled_at', [$startDate, $endDate])
            ->count();

        // Enrollment status ratios
        $enrollmentStats = (clone $enrollmentQuery)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN enrollments.status = "active" THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN enrollments.status = "completed" THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN enrollments.status = "transferred" THEN 1 ELSE 0 END) as transferred_count,
                SUM(CASE WHEN enrollments.status = "dropped" THEN 1 ELSE 0 END) as dropped_count
            ')
            ->first();

        $total = $enrollmentStats->total ?: 1; // Avoid division by zero

        return [
            'classes_open' => ['total' => $openClasses],
            'classes_closed' => ['total' => $closedClasses],
            'new_enrollments' => ['total' => $newEnrollments],
            'completion_rate' => ['rate' => round(($enrollmentStats->completed_count / $total) * 100, 1)],
            'dropout_rate' => ['rate' => round(($enrollmentStats->dropped_count / $total) * 100, 1)],
            'transfer_rate' => ['rate' => round(($enrollmentStats->transferred_count / $total) * 100, 1)],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds)
    {
        // Monthly enrollments by course
        $enrollmentsByCourse = $this->getEnrollmentsByCourse($startDate, $endDate, $branchIds, $courseIds);

        // Top classes by student count
        $topClassesByStudents = $this->getTopClassesByStudents($branchIds, $courseIds);

        // Enrollment status distribution
        $enrollmentStatusDistribution = $this->getEnrollmentStatusDistribution($branchIds, $courseIds);

        return [
            'enrollments_by_course' => $enrollmentsByCourse,
            'top_classes_by_students' => $topClassesByStudents,
            'enrollment_status_distribution' => $enrollmentStatusDistribution,
        ];
    }

    private function getTablesData($startDate, $endDate, $branchIds, $courseIds, $request)
    {
        // Classes summary
        $classesSummary = $this->getClassesSummary($branchIds, $courseIds);

        // New enrollments
        $newEnrollments = $this->getNewEnrollments($startDate, $endDate, $branchIds, $courseIds);

        return [
            'classes_summary' => $classesSummary,
            'new_enrollments' => $newEnrollments,
        ];
    }

    private function buildClassQuery($branchIds = [], $courseIds = [])
    {
        $query = Classroom::query()
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->join('courses', 'classrooms.course_id', '=', 'courses.id');

        if (!empty($branchIds)) {
            $query->whereIn('branches.id', $branchIds);
        }

        if (!empty($courseIds)) {
            $query->whereIn('courses.id', $courseIds);
        }

        return $query;
    }

    private function buildEnrollmentQuery($branchIds = [], $courseIds = [])
    {
        $query = Enrollment::query()
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->join('courses', 'classrooms.course_id', '=', 'courses.id');

        if (!empty($branchIds)) {
            $query->whereIn('branches.id', $branchIds);
        }

        if (!empty($courseIds)) {
            $query->whereIn('courses.id', $courseIds);
        }

        return $query;
    }

    private function getEnrollmentsByCourse($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds)
            ->whereBetween('enrollments.enrolled_at', [$startDate, $endDate]);

        $diffInDays = $startDate->diffInDays($endDate);

        if ($diffInDays <= 31) {
            // Daily grouping
            $results = $query
                ->selectRaw('
                    DATE(enrollments.enrolled_at) as period,
                    courses.name as course_name,
                    COUNT(*) as enrollments_count
                ')
                ->groupBy('period', 'courses.id', 'courses.name')
                ->orderBy('period')
                ->get()
                ->groupBy('period');
        } else {
            // Monthly grouping
            $results = $query
                ->selectRaw('
                    DATE_FORMAT(enrollments.enrolled_at, "%Y-%m") as period,
                    courses.name as course_name,
                    COUNT(*) as enrollments_count
                ')
                ->groupBy('period', 'courses.id', 'courses.name')
                ->orderBy('period')
                ->get()
                ->groupBy('period');
        }

        $courses = Course::whereIn('id', $courseIds)->orWhere(function($q) use ($courseIds) {
            if (empty($courseIds)) $q->where('active', true);
        })->pluck('name')->toArray();

        return $results->map(function($periodData, $period) use ($courses, $diffInDays) {
            $data = [
                'period' => $diffInDays <= 31
                    ? Carbon::parse($period)->format('M j')
                    : Carbon::createFromFormat('Y-m', $period)->format('M Y')
            ];

            // Initialize all courses with 0
            foreach($courses as $course) {
                $data[$course] = 0;
            }

            // Fill actual data
            foreach($periodData as $item) {
                $data[$item->course_name] = (int) $item->enrollments_count;
            }

            return $data;
        })->values();
    }

    private function getTopClassesByStudents($branchIds, $courseIds)
    {
        $query = $this->buildClassQuery($branchIds, $courseIds)
            ->leftJoin('enrollments', 'classrooms.id', '=', 'enrollments.class_id')
            ->where('enrollments.status', 'active');

        return $query
            ->selectRaw('
                classrooms.code,
                classrooms.name as class_name,
                courses.name as course_name,
                branches.name as branch_name,
                COUNT(enrollments.id) as student_count
            ')
            ->groupBy('classrooms.id', 'classrooms.code', 'classrooms.name', 'courses.name', 'branches.name')
            ->orderByDesc('student_count')
            ->limit(10)
            ->get()
            ->map(function($item) {
                return [
                    'name' => "{$item->class_name} ({$item->course_name})",
                    'value' => (int) $item->student_count,
                    'branch' => $item->branch_name
                ];
            });
    }

    private function getEnrollmentStatusDistribution($branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds);

        return $query
            ->selectRaw('
                enrollments.status,
                COUNT(*) as count
            ')
            ->groupBy('enrollments.status')
            ->get()
            ->map(function($item) {
                return [
                    'name' => ucfirst($item->status),
                    'value' => (int) $item->count
                ];
            });
    }

    private function getClassesSummary($branchIds, $courseIds)
    {
        $query = $this->buildClassQuery($branchIds, $courseIds)
            ->leftJoin('enrollments', function($join) {
                $join->on('classrooms.id', '=', 'enrollments.class_id')
                     ->where('enrollments.status', '=', 'active');
            })
            ->leftJoin('teaching_assignments', function($join) {
                $join->on('classrooms.id', '=', 'teaching_assignments.class_id')
                     ->whereNull('teaching_assignments.effective_to');
            })
            ->leftJoin('users as teachers', 'teaching_assignments.teacher_id', '=', 'teachers.id');

        return $query
            ->selectRaw('
                classrooms.id,
                classrooms.code,
                classrooms.name as class_name,
                branches.name as branch_name,
                courses.name as course_name,
                classrooms.status,
                classrooms.start_date,
                COUNT(enrollments.id) as student_count,
                teachers.name as teacher_name
            ')
            ->groupBy('classrooms.id', 'classrooms.code', 'classrooms.name', 'branches.name', 'courses.name', 'classrooms.status', 'classrooms.start_date', 'teachers.name')
            ->orderBy('classrooms.start_date', 'desc')
            ->limit(50)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->class_name,
                    'branch' => $item->branch_name,
                    'course' => $item->course_name,
                    'status' => $item->status,
                    'start_date' => $item->start_date,
                    'student_count' => (int) $item->student_count,
                    'teacher' => $item->teacher_name ?? 'Chưa phân công',
                ];
            });
    }

    private function getNewEnrollments($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds)
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->whereBetween('enrollments.enrolled_at', [$startDate, $endDate]);

        return $query
            ->select([
                'students.id',
                'students.code as student_code',
                'students.name as student_name',
                'classrooms.code as class_code',
                'classrooms.name as class_name',
                'enrollments.enrolled_at',
                'branches.name as branch_name'
            ])
            ->orderBy('enrollments.enrolled_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function($item) {
                return [
                    'student_code' => $item->student_code,
                    'student_name' => $item->student_name,
                    'class' => "{$item->class_code} - {$item->class_name}",
                    'branch' => $item->branch_name,
                    'enrolled_at' => Carbon::parse($item->enrolled_at)->format('d/m/Y'),
                ];
            });
    }
}

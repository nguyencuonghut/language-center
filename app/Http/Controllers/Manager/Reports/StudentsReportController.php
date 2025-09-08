<?php

namespace App\Http\Controllers\Manager\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Enrollment;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Branch;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StudentsReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'courses' => 'nullable|array',
            'courses.*' => 'exists:courses,id',
        ]);

        // Get manager's branch IDs
        $user = Auth::user();
        $branchIds = DB::table('manager_branch')
            ->where('user_id', $user->id)
            ->pluck('branch_id')
            ->all();

        // Default to current month if no dates provided
        $startDate = $request->start_date ? Carbon::parse($request->start_date) : Carbon::now()->startOfMonth();
        $endDate = $request->end_date ? Carbon::parse($request->end_date) : Carbon::now()->endOfMonth();

        $courseIds = $request->courses ?? [];

        // Get filter options
        $courses = Course::select('id', 'name')->where('active', true)->orderBy('name')->get();
        $branches = Branch::select('id', 'name')->whereIn('id', $branchIds)->orderBy('name')->get();

        // Calculate KPIs
        $kpi = $this->calculateKPIs($startDate, $endDate, $branchIds, $courseIds);

        // Get charts data
        $charts = $this->getChartsData($startDate, $endDate, $branchIds, $courseIds);

        // Get recent data
        $recent = $this->getRecentData($startDate, $endDate, $branchIds, $courseIds, $request);

        return Inertia::render('Manager/Reports/Students', [
            'appliedFilters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'courses' => $courseIds,
            ],
            'availableFilters' => [
                'courses' => $courses,
                'branches' => $branches,
            ],
            'kpi' => $kpi,
            'charts' => $charts,
            'recent' => $recent,
        ]);
    }

    private function calculateKPIs($startDate, $endDate, $branchIds, $courseIds)
    {
        $enrollmentQuery = $this->buildEnrollmentQuery($branchIds, $courseIds);

        // Total students (active enrollments)
        $totalStudents = (clone $enrollmentQuery)
            ->where('enrollments.status', 'active')
            ->distinct('enrollments.student_id')
            ->count('enrollments.student_id');

        // Active students (same as total for now)
        $activeStudents = $totalStudents;

        // New enrollments in period
        $newEnrollments = (clone $enrollmentQuery)
            ->whereBetween('enrollments.enrolled_at', [$startDate, $endDate])
            ->count();

        // Attendance rate
        $totalSessions = Attendance::query()
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->when(!empty($courseIds), function($query) use ($courseIds) {
                return $query->whereIn('classrooms.course_id', $courseIds);
            })
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->count();

        $presentSessions = Attendance::query()
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->when(!empty($courseIds), function($query) use ($courseIds) {
                return $query->whereIn('classrooms.course_id', $courseIds);
            })
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->where('attendances.status', 'present')
            ->count();

        $attendanceRate = $totalSessions > 0 ? round(($presentSessions / $totalSessions) * 100, 1) : 0;

        return [
            'total_students' => ['total' => $totalStudents],
            'active_students' => ['total' => $activeStudents],
            'new_enrollments' => ['total' => $newEnrollments],
            'avg_attendance' => ['total' => $attendanceRate],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds)
    {
        // Enrollment trend by month
        $enrollmentTrend = $this->getEnrollmentTrend($startDate, $endDate, $branchIds, $courseIds);

        // Students by course
        $studentsByCourse = $this->getStudentsByCourse($branchIds, $courseIds);

        // Students by branch
        $studentsByBranch = $this->getStudentsByBranch($branchIds, $courseIds);

        // Attendance rate by class
        $attendanceByClass = $this->getAttendanceByClass($startDate, $endDate, $branchIds, $courseIds);

        return [
            'enrollment_trend' => $enrollmentTrend,
            'students_by_course' => $studentsByCourse,
            'students_by_branch' => $studentsByBranch,
            'attendance_by_class' => $attendanceByClass,
        ];
    }

    private function getRecentData($startDate, $endDate, $branchIds, $courseIds, $request)
    {
        // Recent enrollments
        $recentEnrollments = $this->getRecentEnrollments($startDate, $endDate, $branchIds, $courseIds);

        // Students summary
        $studentsSummary = $this->getStudentsSummary($branchIds, $courseIds);

        return [
            'enrollments' => $recentEnrollments,
            'students_summary' => $studentsSummary,
        ];
    }

    private function buildEnrollmentQuery($branchIds, $courseIds = [])
    {
        $query = Enrollment::query()
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds);

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        return $query;
    }

    private function getEnrollmentTrend($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds)
            ->whereBetween('enrollments.enrolled_at', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                DATE_FORMAT(enrollments.enrolled_at, "%Y-%m") as month,
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

    private function getStudentsByCourse($branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds)
            ->join('courses', 'classrooms.course_id', '=', 'courses.id')
            ->where('enrollments.status', 'active');

        return $query
            ->selectRaw('
                courses.name as course_name,
                COUNT(DISTINCT enrollments.student_id) as student_count
            ')
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('student_count')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->course_name,
                    'value' => (int) $item->student_count
                ];
            });
    }

    private function getStudentsByBranch($branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds)
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->where('enrollments.status', 'active');

        return $query
            ->selectRaw('
                branches.name as branch_name,
                COUNT(DISTINCT enrollments.student_id) as student_count
            ')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('student_count')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->branch_name,
                    'value' => (int) $item->student_count
                ];
            });
    }

    private function getAttendanceByClass($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = DB::table('attendances')
            ->join('class_sessions', 'attendances.class_session_id', '=', 'class_sessions.id')
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->when(!empty($courseIds), function($query) use ($courseIds) {
                return $query->whereIn('classrooms.course_id', $courseIds);
            })
            ->whereBetween('class_sessions.date', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                CONCAT(classrooms.code, " - ", classrooms.name) as class_name,
                COUNT(*) as total_attendances,
                SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count
            ')
            ->groupBy('classrooms.id', 'classrooms.code', 'classrooms.name')
            ->having('total_attendances', '>', 0)
            ->orderByDesc('present_count')
            ->limit(10)
            ->get();

        return $results->map(function($item) {
            $rate = $item->total_attendances > 0
                ? round(($item->present_count / $item->total_attendances) * 100, 1)
                : 0;

            return [
                'name' => $item->class_name,
                'rate' => $rate
            ];
        });
    }

    private function getRecentEnrollments($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds)
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('courses', 'classrooms.course_id', '=', 'courses.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->whereBetween('enrollments.enrolled_at', [$startDate, $endDate]);

        return $query
            ->select([
                'enrollments.id',
                'students.name as student_name',
                'students.phone as student_phone',
                'courses.name as course_name',
                'branches.name as branch_name',
                'classrooms.code as class_code',
                'classrooms.name as class_name',
                'enrollments.enrolled_at',
                'enrollments.status'
            ])
            ->orderByDesc('enrollments.enrolled_at')
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'student_name' => $item->student_name,
                    'student_phone' => $item->student_phone,
                    'course_name' => $item->course_name,
                    'branch_name' => $item->branch_name,
                    'class_code' => $item->class_code,
                    'class_name' => $item->class_name ? $item->class_code . ' - ' . $item->class_name : $item->class_code,
                    'enrolled_at' => $item->enrolled_at,
                    'status' => $item->status,
                ];
            });
    }

    private function getStudentsSummary($branchIds, $courseIds)
    {
        $query = $this->buildEnrollmentQuery($branchIds, $courseIds)
            ->join('students', 'enrollments.student_id', '=', 'students.id')
            ->join('courses', 'classrooms.course_id', '=', 'courses.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id');

        return $query
            ->selectRaw('
                students.id,
                students.name as student_name,
                students.phone as student_phone,
                students.email as student_email,
                courses.name as course_name,
                branches.name as branch_name,
                classrooms.code as class_code,
                enrollments.status,
                enrollments.enrolled_at
            ')
            ->orderBy('students.name')
            ->get()
            ->map(function($item) {
                $statusNames = [
                    'active' => 'Đang học',
                    'pending' => 'Chờ xử lý',
                    'suspended' => 'Tạm dừng',
                    'completed' => 'Hoàn thành',
                    'cancelled' => 'Đã hủy'
                ];

                return [
                    'id' => $item->id,
                    'name' => $item->student_name,
                    'phone' => $item->student_phone,
                    'email' => $item->student_email,
                    'course' => $item->course_name,
                    'branch' => $item->branch_name,
                    'class_code' => $item->class_code,
                    'status' => $statusNames[$item->status] ?? ucfirst($item->status),
                    'enrolled_at' => Carbon::parse($item->enrolled_at)->format('d/m/Y'),
                ];
            });
    }
}

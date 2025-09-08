<?php

namespace App\Http\Controllers\Manager\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Course;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ClassesReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'courses' => 'nullable|array',
            'courses.*' => 'exists:courses,id',
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

        // Get filter options
        $courses = Course::select('id', 'name')->where('active', true)->orderBy('name')->get();

        // Calculate KPIs
        $kpi = $this->calculateKPIs($startDate, $endDate, $branchIds, $courseIds);

        // Get charts data
        $charts = $this->getChartsData($startDate, $endDate, $branchIds, $courseIds);

        // Get recent data
        $recent = $this->getRecentData($startDate, $endDate, $branchIds, $courseIds, $request);

        return Inertia::render('Manager/Reports/Classes', [
            'appliedFilters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'courses' => $courseIds,
            ],
            'availableFilters' => [
                'courses' => $courses,
            ],
            'kpi' => $kpi,
            'charts' => $charts,
            'recent' => $recent,
        ]);
    }

    private function calculateKPIs($startDate, $endDate, $branchIds, $courseIds)
    {
        $classQuery = $this->buildClassQuery($branchIds, $courseIds);

        // Open classes
        $openClasses = (clone $classQuery)
            ->whereIn('status', ['open', 'active'])
            ->count();

        // Average capacity utilization
        $classEnrollments = (clone $classQuery)
            ->leftJoin('enrollments', function($join) {
                $join->on('classrooms.id', '=', 'enrollments.class_id')
                     ->where('enrollments.status', '=', 'active');
            })
            ->selectRaw('classrooms.id, COUNT(enrollments.id) as enrollment_count')
            ->groupBy('classrooms.id')
            ->get();

        $totalClasses = $classEnrollments->count();
        $totalEnrollments = $classEnrollments->sum('enrollment_count');
        $capacityUtilization = $totalClasses > 0 ? ($totalEnrollments / $totalClasses) : 0;

        // Assume average capacity is 15 students
        $avgCapacityRate = round(($capacityUtilization / 15) * 100, 1);

        // Canceled/moved sessions in date range
        $canceledMovedSessions = ClassSession::query()
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->when(!empty($courseIds), function($query) use ($courseIds) {
                return $query->whereIn('classrooms.course_id', $courseIds);
            })
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->whereIn('class_sessions.status', ['canceled', 'moved'])
            ->count();

        return [
            'open_classes' => ['total' => $openClasses],
            'avg_capacity_rate' => ['rate' => $avgCapacityRate],
            'canceled_moved_sessions' => ['total' => $canceledMovedSessions],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds)
    {
        // Student count by class
        $studentsByClass = $this->getStudentsByClass($branchIds, $courseIds);

        // Monthly canceled/moved sessions trend
        $canceledMovedTrend = $this->getCanceledMovedTrend($startDate, $endDate, $branchIds, $courseIds);

        // Room utilization
        $roomUtilization = $this->getRoomUtilization($branchIds);

        return [
            'students_by_class' => $studentsByClass,
            'canceled_moved_trend' => $canceledMovedTrend,
            'room_utilization' => $roomUtilization,
        ];
    }

    private function getRecentData($startDate, $endDate, $branchIds, $courseIds, $request)
    {
        // Recent classes
        $recentClasses = $this->getRecentClasses($branchIds, $courseIds);

        // Classes summary
        $classesSummary = $this->getClassesSummary($branchIds, $courseIds);

        return [
            'classes' => $recentClasses,
            'classes_summary' => $classesSummary,
        ];
    }

    private function buildClassQuery($branchIds, $courseIds = [])
    {
        $query = Classroom::query()
            ->whereIn('branch_id', $branchIds);

        if (!empty($courseIds)) {
            $query->whereIn('course_id', $courseIds);
        }

        return $query;
    }

    private function getStudentsByClass($branchIds, $courseIds)
    {
        $query = $this->buildClassQuery($branchIds, $courseIds)
            ->join('courses', 'classrooms.course_id', '=', 'courses.id')
            ->leftJoin('enrollments', function($join) {
                $join->on('classrooms.id', '=', 'enrollments.class_id')
                     ->where('enrollments.status', '=', 'active');
            });

        return $query
            ->selectRaw('
                CONCAT(classrooms.code, " - ", classrooms.name) as class_name,
                courses.name as course_name,
                COUNT(enrollments.id) as student_count
            ')
            ->groupBy('classrooms.id', 'classrooms.code', 'classrooms.name', 'courses.name')
            ->orderByDesc('student_count')
            ->limit(15)
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->class_name,
                    'course' => $item->course_name,
                    'value' => (int) $item->student_count
                ];
            });
    }

    private function getCanceledMovedTrend($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = ClassSession::query()
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->when(!empty($courseIds), function($query) use ($courseIds) {
                return $query->whereIn('classrooms.course_id', $courseIds);
            })
            ->whereBetween('class_sessions.date', [$startDate, $endDate])
            ->whereIn('class_sessions.status', ['canceled', 'moved']);

        $results = $query
            ->selectRaw('
                DATE_FORMAT(class_sessions.date, "%Y-%m") as month,
                class_sessions.status,
                COUNT(*) as count
            ')
            ->groupBy('month', 'class_sessions.status')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        return $results->map(function($monthData, $month) {
            $data = [
                'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'canceled' => 0,
                'moved' => 0,
            ];

            foreach($monthData as $item) {
                $data[$item->status] = (int) $item->count;
            }

            return $data;
        })->values();
    }

    private function getRoomUtilization($branchIds)
    {
        // Get room usage frequency
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();

        $results = Room::query()
            ->whereIn('branch_id', $branchIds)
            ->leftJoin('class_sessions', 'rooms.id', '=', 'class_sessions.room_id')
            ->whereBetween('class_sessions.date', [$monthStart, $monthEnd])
            ->where('class_sessions.status', '!=', 'canceled')
            ->selectRaw('
                rooms.name as room_name,
                COUNT(class_sessions.id) as usage_count
            ')
            ->groupBy('rooms.id', 'rooms.name')
            ->orderByDesc('usage_count')
            ->get();

        return $results->map(function($item) {
            return [
                'name' => $item->room_name,
                'value' => (int) $item->usage_count
            ];
        });
    }

    private function getRecentClasses($branchIds, $courseIds)
    {
        $query = $this->buildClassQuery($branchIds, $courseIds)
            ->join('courses', 'classrooms.course_id', '=', 'courses.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
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
                courses.name as course_name,
                branches.name as branch_name,
                classrooms.status,
                COUNT(enrollments.id) as students_count,
                teachers.name as teacher_name
            ')
            ->groupBy('classrooms.id', 'classrooms.code', 'classrooms.name', 'courses.name', 'branches.name', 'classrooms.status', 'teachers.name')
            ->orderBy('classrooms.start_date', 'desc')
            ->limit(20)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->class_name ? $item->code . ' - ' . $item->class_name : $item->code,
                    'course_name' => $item->course_name,
                    'branch_name' => $item->branch_name,
                    'status' => $item->status,
                    'students_count' => (int) $item->students_count,
                    'teacher_name' => $item->teacher_name,
                ];
            });
    }

    private function getClassesSummary($branchIds, $courseIds)
    {
        $query = $this->buildClassQuery($branchIds, $courseIds)
            ->join('courses', 'classrooms.course_id', '=', 'courses.id')
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
                courses.name as course_name,
                classrooms.status,
                COUNT(enrollments.id) as student_count,
                teachers.name as teacher_name
            ')
            ->groupBy('classrooms.id', 'classrooms.code', 'classrooms.name', 'courses.name', 'classrooms.status', 'teachers.name')
            ->orderBy('classrooms.status')
            ->orderBy('classrooms.start_date', 'desc')
            ->get()
            ->map(function($item) {
                $statusNames = [
                    'open' => 'Mở',
                    'active' => 'Đang học',
                    'closed' => 'Đã đóng',
                    'completed' => 'Hoàn thành',
                    'cancelled' => 'Đã hủy'
                ];

                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'name' => $item->class_name,
                    'course' => $item->course_name,
                    'status' => $statusNames[$item->status] ?? ucfirst($item->status),
                    'student_count' => (int) $item->student_count,
                    'teacher' => $item->teacher_name ?? 'Chưa phân công',
                ];
            });
    }
}

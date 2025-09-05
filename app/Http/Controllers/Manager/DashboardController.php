<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\Transfer;
use App\Models\User;
use App\Models\Attendance;
use App\Models\TeacherTimesheet;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        // Get first manager user for testing when no auth
        $user = $request->user() ?? User::where('email', 'manager1@honghafeed.com.vn')->first() ?? User::first();
        $branchIds = $user->managerBranches()->pluck('branches.id')->all();

        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        if (empty($branchIds)) {
            return Inertia::render('Manager/Dashboard', [
                'kpi' => [
                    'students' => ['total' => 0, 'growth' => 0],
                    'classes' => ['total' => 0, 'growth' => 0],
                    'teachers' => ['total' => 0, 'growth' => 0],
                    'sessions_today' => ['total' => 0, 'growth' => 0],
                ],
                'charts' => [
                    'enrollment_trend' => [],
                    'attendance_by_class' => [],
                    'students_by_course' => [],
                ],
                'recent' => [
                    'transfers' => [],
                    'attendance_today' => [],
                    'pending_timesheets' => [],
                ],
                'alerts' => [
                    'low_attendance_classes' => 0,
                    'pending_transfers' => 0,
                    'overdue_timesheets' => 0,
                ],
                'meta' => [
                    'today' => $today->toDateString(),
                    'month_range' => [$monthStart->toDateString(), $monthEnd->toDateString()],
                    'branch_names' => [],
                ],
            ]);
        }

        // Calculate KPIs with growth
        $kpi = $this->calculateKPIs($branchIds, $today, $monthStart, $monthEnd, $lastMonthStart, $lastMonthEnd);

        // Charts data
        $charts = $this->getChartsData($branchIds);

        // Recent activities
        $recent = $this->getRecentActivities($branchIds, $today);

        // Alerts
        $alerts = $this->getAlerts($branchIds, $today);

        return Inertia::render('Manager/Dashboard', [
            'kpi' => $kpi,
            'charts' => $charts,
            'recent' => $recent,
            'alerts' => $alerts,
            'meta' => [
                'today' => $today->toDateString(),
                'month_range' => [$monthStart->toDateString(), $monthEnd->toDateString()],
                'branch_names' => $user->managerBranches()->pluck('name')->toArray(),
            ],
        ]);
    }    private function calculateKPIs($branchIds, $today, $monthStart, $monthEnd, $lastMonthStart, $lastMonthEnd)
    {
        // Current counts - scoped to manager's branches
        $studentsCount = DB::table('students')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->where('enrollments.status', 'active')
            ->distinct('students.id')
            ->count();

        $classesCount = Classroom::whereIn('branch_id', $branchIds)
            ->whereIn('status', ['open', 'active'])
            ->count();

        $teachersCount = DB::table('users')
            ->join('teaching_assignments', 'users.id', '=', 'teaching_assignments.teacher_id')
            ->join('classrooms', 'teaching_assignments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->where('users.active', true)
            ->whereNull('teaching_assignments.effective_to')
            ->distinct('users.id')
            ->count();

        $sessionsToday = ClassSession::whereDate('date', $today)
            ->whereHas('classroom', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->count();

        // Previous month counts for growth calculation
        $lastStudentsCount = DB::table('students')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->where('enrollments.status', 'active')
            ->where('enrollments.enrolled_at', '<', $monthStart)
            ->distinct('students.id')
            ->count();

        $lastClassesCount = Classroom::whereIn('branch_id', $branchIds)
            ->whereIn('status', ['open', 'active'])
            ->where('created_at', '<', $monthStart)
            ->count();

        // Growth calculations
        $studentsGrowth = $lastStudentsCount > 0 ? round((($studentsCount - $lastStudentsCount) / $lastStudentsCount) * 100, 1) : 0;
        $classesGrowth = $lastClassesCount > 0 ? round((($classesCount - $lastClassesCount) / $lastClassesCount) * 100, 1) : 0;

        return [
            'students' => ['total' => $studentsCount, 'growth' => $studentsGrowth],
            'classes' => ['total' => $classesCount, 'growth' => $classesGrowth],
            'teachers' => ['total' => $teachersCount, 'growth' => 0],
            'sessions_today' => ['total' => $sessionsToday, 'growth' => 0],
        ];
    }

    private function getChartsData($branchIds)
    {
        // Enrollment trend (last 6 months)
        $enrollmentTrend = collect();
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            $enrollments = Enrollment::whereBetween('enrolled_at', [$monthStart, $monthEnd])
                ->whereHas('classroom', function ($q) use ($branchIds) {
                    $q->whereIn('branch_id', $branchIds);
                })
                ->count();
            $enrollmentTrend->push([
                'month' => $monthStart->format('M Y'),
                'value' => $enrollments
            ]);
        }

        // Attendance rate by class (last 30 days)
        $attendanceByClass = DB::table('classrooms')
            ->leftJoin('class_sessions', 'classrooms.id', '=', 'class_sessions.class_id')
            ->leftJoin('attendances', 'class_sessions.id', '=', 'attendances.class_session_id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->where('class_sessions.date', '>=', Carbon::now()->subDays(30))
            ->select(
                'classrooms.name',
                DB::raw('COUNT(attendances.id) as total_attendances'),
                DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count')
            )
            ->groupBy('classrooms.id', 'classrooms.name')
            ->havingRaw('COUNT(attendances.id) > 0')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'rate' => $item->total_attendances > 0 ? round(($item->present_count / $item->total_attendances) * 100, 1) : 0
                ];
            });

        // Students by course
        $studentsByCourse = DB::table('courses')
            ->leftJoin('classrooms', 'courses.id', '=', 'classrooms.course_id')
            ->leftJoin('enrollments', function($join) {
                $join->on('classrooms.id', '=', 'enrollments.class_id')
                     ->where('enrollments.status', '=', 'active');
            })
            ->whereIn('classrooms.branch_id', $branchIds)
            ->select('courses.name', DB::raw('COUNT(DISTINCT enrollments.student_id) as students_count'))
            ->groupBy('courses.id', 'courses.name')
            ->havingRaw('COUNT(DISTINCT enrollments.student_id) > 0')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->name,
                    'value' => (int) $item->students_count
                ];
            });

        return [
            'enrollment_trend' => $enrollmentTrend,
            'attendance_by_class' => $attendanceByClass,
            'students_by_course' => $studentsByCourse,
        ];
    }

    private function getRecentActivities($branchIds, $today)
    {
        // Recent transfers
        $recentTransfers = Transfer::with([
            'student:id,code,name',
            'fromClass:id,code,name',
            'toClass:id,code,name'
        ])
            ->whereHas('fromClass', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->orWhereHas('toClass', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->latest()
            ->limit(5)
            ->get(['id', 'student_id', 'from_class_id', 'to_class_id', 'status', 'created_at']);

        // Today's attendance summary
        $attendanceToday = DB::table('class_sessions')
            ->join('classrooms', 'class_sessions.class_id', '=', 'classrooms.id')
            ->leftJoin('attendances', 'class_sessions.id', '=', 'attendances.class_session_id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->whereDate('class_sessions.date', $today)
            ->select(
                'classrooms.name as class_name',
                'class_sessions.start_time',
                'class_sessions.end_time',
                DB::raw('COUNT(attendances.id) as total_attendances'),
                DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count')
            )
            ->groupBy('class_sessions.id', 'classrooms.name', 'class_sessions.start_time', 'class_sessions.end_time')
            ->limit(10)
            ->get();

        // Pending timesheets for approval
        $pendingTimesheets = TeacherTimesheet::with([
            'teacher:id,name',
            'session.classroom:id,name'
        ])
            ->where('status', 'draft')
            ->whereHas('session.classroom', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->latest()
            ->limit(5)
            ->get(['id', 'teacher_id', 'class_session_id', 'amount', 'created_at']);

        return [
            'transfers' => $recentTransfers,
            'attendance_today' => $attendanceToday,
            'pending_timesheets' => $pendingTimesheets,
        ];
    }

    private function getAlerts($branchIds, $today)
    {
        // Classes with low attendance (< 70% in last 7 days)
        $lowAttendanceClasses = DB::table('classrooms')
            ->leftJoin('class_sessions', 'classrooms.id', '=', 'class_sessions.class_id')
            ->leftJoin('attendances', 'class_sessions.id', '=', 'attendances.class_session_id')
            ->whereIn('classrooms.branch_id', $branchIds)
            ->where('class_sessions.date', '>=', Carbon::now()->subDays(7))
            ->select(
                'classrooms.id',
                DB::raw('COUNT(attendances.id) as total_attendances'),
                DB::raw('SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) as present_count')
            )
            ->groupBy('classrooms.id')
            ->havingRaw('COUNT(attendances.id) > 0')
            ->havingRaw('(SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) / COUNT(attendances.id)) < 0.7')
            ->count();

        // Pending transfers
        $pendingTransfers = Transfer::whereHas('fromClass', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->orWhereHas('toClass', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->where('status', 'pending')
            ->count();

        // Overdue timesheets (draft more than 3 days ago)
        $overdueTimesheets = TeacherTimesheet::where('status', 'draft')
            ->where('created_at', '<', Carbon::now()->subDays(3))
            ->whereHas('session.classroom', function ($q) use ($branchIds) {
                $q->whereIn('branch_id', $branchIds);
            })
            ->count();

        return [
            'low_attendance_classes' => $lowAttendanceClasses,
            'pending_transfers' => $pendingTransfers,
            'overdue_timesheets' => $overdueTimesheets,
        ];
    }
}

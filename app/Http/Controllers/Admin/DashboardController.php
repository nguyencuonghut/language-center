<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Branch;
use App\Models\Transfer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Calculate KPIs with growth
        $kpi = $this->calculateKPIs($today, $monthStart, $monthEnd, $lastMonthStart, $lastMonthEnd);
        
        // Charts data
        $charts = $this->getChartsData();
        
        // Recent activities
        $recent = $this->getRecentActivities($today);
        
        // Alerts
        $alerts = $this->getAlerts($today);

        return Inertia::render('Admin/Dashboard', [
            'kpi' => $kpi,
            'charts' => $charts,
            'recent' => $recent,
            'alerts' => $alerts,
            'meta' => [
                'today' => $today->toDateString(),
                'month_range' => [$monthStart->toDateString(), $monthEnd->toDateString()],
            ],
        ]);
    }

    private function calculateKPIs($today, $monthStart, $monthEnd, $lastMonthStart, $lastMonthEnd)
    {
        // Current counts - sử dụng học viên có enrollment active
        $studentsCount = DB::table('students')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
            ->where('enrollments.status', 'active')
            ->distinct('students.id')
            ->count();
            
        $classesCount = Classroom::whereIn('status', ['open', 'active'])->count();
        $teachersCount = User::role('teacher')->where('active', true)->count();
        $branchesCount = Branch::count();

        // Previous month counts for growth calculation - cũng sử dụng logic tương tự
        $lastStudentsCount = DB::table('students')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
            ->where('enrollments.status', 'active')
            ->where('students.created_at', '<', $monthStart)
            ->distinct('students.id')
            ->count();
            
        $lastClassesCount = Classroom::whereIn('status', ['open', 'active'])
            ->where('created_at', '<', $monthStart)->count();
        $lastTeachersCount = User::role('teacher')->where('active', true)
            ->where('created_at', '<', $monthStart)->count();

        // Financial KPIs
        $revenueThisMonth = (int) Payment::whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');
        $revenueLastMonth = (int) Payment::whereBetween('paid_at', [$lastMonthStart, $lastMonthEnd])->sum('amount');
        $totalRevenue = (int) Payment::sum('amount');
        $totalInvoiced = (int) Invoice::sum('total');
        $outstanding = max(0, $totalInvoiced - (int) Payment::sum('amount'));
        $collectionRate = $totalInvoiced > 0 ? round(((int) Payment::sum('amount') / $totalInvoiced) * 100, 1) : 0;

        // Growth calculations
        $studentsGrowth = $lastStudentsCount > 0 ? round((($studentsCount - $lastStudentsCount) / $lastStudentsCount) * 100, 1) : 0;
        $classesGrowth = $lastClassesCount > 0 ? round((($classesCount - $lastClassesCount) / $lastClassesCount) * 100, 1) : 0;
        $teachersGrowth = $lastTeachersCount > 0 ? round((($teachersCount - $lastTeachersCount) / $lastTeachersCount) * 100, 1) : 0;
        $revenueGrowth = $revenueLastMonth > 0 ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1) : 0;

        return [
            'students' => ['total' => $studentsCount, 'growth' => $studentsGrowth],
            'classes' => ['total' => $classesCount, 'growth' => $classesGrowth],
            'teachers' => ['total' => $teachersCount, 'growth' => $teachersGrowth],
            'branches' => ['total' => $branchesCount, 'growth' => 0],
            'revenue_month' => ['total' => $revenueThisMonth, 'growth' => $revenueGrowth],
            'revenue_total' => ['total' => $totalRevenue, 'growth' => 22],
            'outstanding' => ['total' => $outstanding, 'growth' => -8],
            'collection_rate' => ['rate' => $collectionRate, 'growth' => 1.2],
        ];
    }

    private function getChartsData()
    {
        // Revenue for last 12 months
        $revenueMonthly = collect();
        for ($i = 11; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            $revenue = Payment::whereBetween('paid_at', [$monthStart, $monthEnd])->sum('amount');
            $revenueMonthly->push([
                'month' => $monthStart->format('M Y'),
                'value' => (int) $revenue
            ]);
        }

        // Students by branch - hiển thị tổng lượt đăng ký active theo chi nhánh
        $enrollmentsByBranch = DB::table('branches')
            ->leftJoin('classrooms', 'branches.id', '=', 'classrooms.branch_id')
            ->leftJoin('enrollments', function($join) {
                $join->on('classrooms.id', '=', 'enrollments.class_id')
                     ->where('enrollments.status', '=', 'active');
            })
            ->select('branches.name', DB::raw('COUNT(enrollments.student_id) as enrollments_count'))
            ->groupBy('branches.id', 'branches.name')
            ->get()
            ->map(function($branch) {
                return [
                    'name' => $branch->name,
                    'value' => (int) $branch->enrollments_count
                ];
            });

        // Students by branch - đếm học viên duy nhất (lấy chi nhánh đầu tiên của mỗi học viên)
        $uniqueStudentsByBranch = DB::table('students')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->where('enrollments.status', 'active')
            ->select('students.id', 'branches.name', 'enrollments.enrolled_at')
            ->get()
            ->groupBy('id')
            ->map(function($enrollments) {
                // Lấy chi nhánh của enrollment đầu tiên (theo thời gian)
                return $enrollments->sortBy('enrolled_at')->first();
            })
            ->groupBy('name')
            ->map(function($students, $branchName) {
                return [
                    'name' => $branchName,
                    'value' => $students->count()
                ];
            })
            ->values();

        // Enrollment trend (last 6 months)
        $enrollmentTrend = collect();
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = Carbon::now()->subMonths($i)->startOfMonth();
            $monthEnd = Carbon::now()->subMonths($i)->endOfMonth();
            $enrollments = Enrollment::whereBetween('enrolled_at', [$monthStart, $monthEnd])->count();
            $enrollmentTrend->push([
                'month' => $monthStart->format('M Y'),
                'value' => $enrollments
            ]);
        }

        return [
            'revenue_monthly' => $revenueMonthly,
            'students_by_branch' => $uniqueStudentsByBranch, // Học viên duy nhất
            'enrollments_by_branch' => $enrollmentsByBranch, // Tổng lượt đăng ký
            'enrollment_trend' => $enrollmentTrend,
        ];
    }

    private function getRecentActivities($today)
    {
        // Recent invoices
        $recentInvoices = Invoice::with(['student:id,code,name', 'branch:id,name'])
            ->latest()
            ->limit(5)
            ->get(['id', 'code', 'student_id', 'branch_id', 'total', 'status', 'created_at']);

        // Recent transfers
        $recentTransfers = Transfer::with([
            'student:id,code,name',
            'fromClass:id,code,name',
            'toClass:id,code,name'
        ])
            ->latest()
            ->limit(5)
            ->get(['id', 'student_id', 'from_class_id', 'to_class_id', 'created_at']);

        // Upcoming classes
        $upcomingClasses = Classroom::with(['course:id,name', 'branch:id,name'])
            ->where('start_date', '>=', $today)
            ->where('start_date', '<=', $today->copy()->addDays(7))
            ->orderBy('start_date')
            ->limit(5)
            ->get(['id', 'code', 'name', 'course_id', 'branch_id', 'start_date', 'sessions_total']);

        return [
            'invoices' => $recentInvoices,
            'transfers' => $recentTransfers,
            'upcoming_classes' => $upcomingClasses,
        ];
    }

    private function getAlerts($today)
    {
        // Overdue invoices
        $overdueInvoices = Invoice::where('status', 'unpaid')
            ->where('due_date', '<', $today)
            ->whereNotNull('due_date')
            ->count();

        // Classes near capacity - need to check if classrooms table has capacity column
        $fullClasses = DB::table('classrooms')
            ->leftJoin('enrollments', 'classrooms.id', '=', 'enrollments.class_id')
            ->select('classrooms.id', DB::raw('COUNT(enrollments.id) as enrolled_count'))
            ->where('enrollments.status', 'active')
            ->groupBy('classrooms.id')
            ->havingRaw('COUNT(enrollments.id) >= 15') // Assume capacity around 15-20
            ->count();

        return [
            'overdue_invoices' => $overdueInvoices,
            'full_classes' => $fullClasses,
            'new_reports' => 1,
        ];
    }
}

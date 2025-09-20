<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Transfer;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Classroom;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransfersReportController extends Controller
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

        return Inertia::render('Admin/Reports/Transfers', [
            'appliedFilters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'branch_ids' => $branchIds,
                'course_ids' => $courseIds,
            ],
            'availableFilters' => [
                'branches' => Branch::where('active', true)->get(['id', 'name']),
                'courses' => Course::where('active', true)->get(['id', 'name']),
            ],
            'kpi' => $kpi,
            'charts' => $charts,
            'recent' => [
                'transfers' => []
            ],
        ]);
    }

    private function calculateKPIs($startDate, $endDate, $branchIds, $courseIds)
    {
        $transferQuery = $this->buildTransferQuery($branchIds, $courseIds);

        // Total transfers in date range
        $totalTransfers = (clone $transferQuery)
            ->whereBetween('transfers.created_at', [$startDate, $endDate])
            ->count();
        // Active transfers in date range
        // (Assuming 'active' means transfers that are not reverted or retargeted)
        // This can be adjusted based on actual business logic
        $activeTransfers = (clone $transferQuery)
            ->whereBetween('transfers.created_at', [$startDate, $endDate])
            ->where('transfers.status', 'active')
            ->count();
        // Reverted transfers in date range
        $revertedTransfers = (clone $transferQuery)
            ->whereBetween('transfers.created_at', [$startDate, $endDate])
            ->where('transfers.status', 'reverted')
            ->count();

        // Total students (for transfer rate calculation)
        $totalStudents = DB::table('students')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->join('branches', 'classrooms.branch_id', '=', 'branches.id')
            ->when(!empty($branchIds), function($query) use ($branchIds) {
                return $query->whereIn('branches.id', $branchIds);
            })
            ->when(!empty($courseIds), function($query) use ($courseIds) {
                return $query->whereIn('classrooms.course_id', $courseIds);
            })
            ->where('enrollments.status', 'active')
            ->distinct('students.id')
            ->count();

        // Transfer rate
        $transferRate = $totalStudents > 0 ? round(($totalTransfers / $totalStudents) * 100, 1) : 0;

        // Top transfer reasons
        $topReasons = (clone $transferQuery)
            ->whereBetween('transfers.created_at', [$startDate, $endDate])
            ->whereNotNull('reason')
            ->selectRaw('reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(3)
            ->get();

        return [
            'total_transfers' => ['total' => $totalTransfers],
            'active_transfers' => ['total' => $activeTransfers],
            'reverted_transfers' => ['total' => $revertedTransfers],
            'transfer_rate' => ['rate' => $transferRate],
            'total_students' => ['total' => $totalStudents],
            'top_reasons' => $topReasons->map(function($item) {
                return [
                    'reason' => $item->reason,
                    'count' => (int) $item->count
                ];
            }),
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds)
    {
        // Monthly transfer trend
        $monthlyTransferTrend = $this->getMonthlyTransferTrend($startDate, $endDate, $branchIds, $courseIds);

        // Transfer flow between courses
        $transferFlowByCourse = $this->getTransferFlowByCourse($startDate, $endDate, $branchIds, $courseIds);

        // Transfer flow between branches
        $transferFlowByBranch = $this->getTransferFlowByBranch($startDate, $endDate, $branchIds, $courseIds);

        // Transfer reasons distribution
        $transferReasons = $this->getTransferReasons($startDate, $endDate, $branchIds, $courseIds);

        return [
            'monthly_transfer_trend' => $monthlyTransferTrend,
            'transfer_flow_by_course' => $transferFlowByCourse,
            'transfer_flow_by_branch' => $transferFlowByBranch,
            'transfer_reasons' => $transferReasons,
        ];
    }

    private function getTablesData($startDate, $endDate, $branchIds, $courseIds, $request)
    {
        // Transfer details
        $transferDetails = $this->getTransferDetails($startDate, $endDate, $branchIds, $courseIds);

        return [
            'transfer_details' => $transferDetails,
        ];
    }

    private function buildTransferQuery($branchIds = [], $courseIds = [])
    {
        $query = Transfer::query()
            ->join('students', 'transfers.student_id', '=', 'students.id')
            ->join('classrooms as from_class', 'transfers.from_class_id', '=', 'from_class.id')
            ->join('classrooms as to_class', 'transfers.to_class_id', '=', 'to_class.id')
            ->join('branches as from_branch', 'from_class.branch_id', '=', 'from_branch.id')
            ->join('branches as to_branch', 'to_class.branch_id', '=', 'to_branch.id');

        if (!empty($branchIds)) {
            $query->where(function($q) use ($branchIds) {
                $q->whereIn('from_branch.id', $branchIds)
                  ->orWhereIn('to_branch.id', $branchIds);
            });
        }

        if (!empty($courseIds)) {
            $query->where(function($q) use ($courseIds) {
                $q->whereIn('from_class.course_id', $courseIds)
                  ->orWhereIn('to_class.course_id', $courseIds);
            });
        }

        return $query;
    }

    private function getMonthlyTransferTrend($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildTransferQuery($branchIds, $courseIds)
            ->whereBetween('transfers.created_at', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                DATE_FORMAT(transfers.created_at, "%Y-%m") as month,
                COUNT(*) as transfer_count
            ')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return $results->map(function($item) {
            return [
                'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                'value' => (int) $item->transfer_count
            ];
        });
    }

    private function getTransferFlowByCourse($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildTransferQuery($branchIds, $courseIds)
            ->join('courses as from_course', 'from_class.course_id', '=', 'from_course.id')
            ->join('courses as to_course', 'to_class.course_id', '=', 'to_course.id')
            ->whereBetween('transfers.created_at', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                from_course.name as from_course,
                to_course.name as to_course,
                COUNT(*) as transfer_count
            ')
            ->groupBy('from_course.id', 'from_course.name', 'to_course.id', 'to_course.name')
            ->orderByDesc('transfer_count')
            ->get();

        return $results->map(function($item) {
            return [
                'from' => $item->from_course,
                'to' => $item->to_course,
                'value' => (int) $item->transfer_count
            ];
        });
    }

    private function getTransferFlowByBranch($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildTransferQuery($branchIds, $courseIds)
            ->whereBetween('transfers.created_at', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                from_branch.code as from_branch,
                to_branch.code as to_branch,
                COUNT(*) as transfer_count
            ')
            ->groupBy('from_branch.id', 'from_branch.code', 'to_branch.id', 'to_branch.code')
            ->orderByDesc('transfer_count')
            ->get();

        return $results->map(function($item) {
            return [
                'from' => $item->from_branch,
                'to' => $item->to_branch,
                'value' => (int) $item->transfer_count
            ];
        });
    }

    private function getTransferReasons($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildTransferQuery($branchIds, $courseIds)
            ->whereBetween('transfers.created_at', [$startDate, $endDate])
            ->whereNotNull('reason');

        $results = $query
            ->selectRaw('
                reason,
                COUNT(*) as count
            ')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->get();

        return $results->map(function($item) {
            return [
                'name' => $item->reason,
                'value' => (int) $item->count
            ];
        });
    }

    private function getTransferDetails($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildTransferQuery($branchIds, $courseIds)
            ->leftJoin('users as handler', 'transfers.created_by', '=', 'handler.id')
            ->whereBetween('transfers.created_at', [$startDate, $endDate]);

        return $query
            ->select([
                'transfers.id',
                'transfers.created_at',
                'students.code as student_code',
                'students.name as student_name',
                'from_class.code as from_class_code',
                'from_class.name as from_class_name',
                'to_class.code as to_class_code',
                'to_class.name as to_class_name',
                'from_branch.name as from_branch',
                'to_branch.name as to_branch',
                'transfers.reason',
                'transfers.status',
                'handler.name as handler_name'
            ])
            ->orderBy('transfers.created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'date' => Carbon::parse($item->created_at)->format('d/m/Y'),
                    'student' => "{$item->student_code} - {$item->student_name}",
                    'from_class' => "{$item->from_class_code} - {$item->from_class_name}",
                    'to_class' => "{$item->to_class_code} - {$item->to_class_name}",
                    'from_branch' => $item->from_branch,
                    'to_branch' => $item->to_branch,
                    'reason' => $item->reason ?? 'Không ghi',
                    'status' => ucfirst($item->status),
                    'handler' => $item->handler_name ?? 'Hệ thống',
                ];
            });
    }
}

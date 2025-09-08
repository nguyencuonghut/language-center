<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Branch;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class RevenueReportController extends Controller
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

        return Inertia::render('Admin/Reports/Revenue', [
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
        $query = $this->buildBaseQuery($branchIds, $courseIds);

        // Revenue in date range
        $revenueInRange = (clone $query)
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->sum('payments.amount') ?? 0;

        // This month revenue
        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $revenueThisMonth = (clone $query)
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->whereBetween('payments.paid_at', [$monthStart, $monthEnd])
            ->sum('payments.amount') ?? 0;

        // Invoice counts
        $invoiceStats = (clone $query)
            ->whereBetween('invoices.created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(*) as total_invoices,
                SUM(CASE WHEN invoices.status = "paid" THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN invoices.status = "unpaid" THEN 1 ELSE 0 END) as unpaid_count,
                SUM(CASE WHEN invoices.status = "partial" THEN 1 ELSE 0 END) as partial_count
            ')
            ->first();

        // Outstanding amount - calculate from payments
        $outstanding = (clone $query)
            ->leftJoin('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->selectRaw('
                SUM(invoices.total) as total_invoiced,
                COALESCE(SUM(payments.amount), 0) as total_paid
            ')
            ->first();

        $outstandingAmount = max(0, ($outstanding->total_invoiced ?? 0) - ($outstanding->total_paid ?? 0));

        return [
            'revenue_range' => ['total' => (int) $revenueInRange],
            'revenue_month' => ['total' => (int) $revenueThisMonth],
            'invoices' => [
                'paid' => (int) $invoiceStats->paid_count,
                'unpaid' => (int) $invoiceStats->unpaid_count,
                'partial' => (int) $invoiceStats->partial_count,
                'total' => (int) $invoiceStats->total_invoices,
            ],
            'outstanding' => ['total' => (int) $outstandingAmount],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds)
    {
        // Revenue trend
        $revenueTrend = $this->getRevenueTrend($startDate, $endDate, $branchIds, $courseIds);

        // Invoice status by month
        $invoiceStatusByMonth = $this->getInvoiceStatusByMonth($startDate, $endDate, $branchIds, $courseIds);

        // Revenue by branch or course
        $revenueByBranch = $this->getRevenueByBranch($startDate, $endDate, $branchIds, $courseIds);
        $revenueByCourse = $this->getRevenueByCourse($startDate, $endDate, $branchIds, $courseIds);

        return [
            'revenue_trend' => $revenueTrend,
            'invoice_status_by_month' => $invoiceStatusByMonth,
            'revenue_by_branch' => $revenueByBranch,
            'revenue_by_course' => $revenueByCourse,
        ];
    }

    private function getTablesData($startDate, $endDate, $branchIds, $courseIds, $request)
    {
        // Monthly summary by branch
        $monthlySummary = $this->getMonthlySummaryByBranch($startDate, $endDate, $branchIds, $courseIds);

        return [
            'monthly_summary' => $monthlySummary,
        ];
    }

    private function buildBaseQuery($branchIds = [], $courseIds = [])
    {
        $query = Invoice::query()
            ->join('branches', 'invoices.branch_id', '=', 'branches.id');

        // Only join classroom and course if we need course filtering
        if (!empty($courseIds)) {
            $query->join('classrooms', 'invoices.class_id', '=', 'classrooms.id');
        }

        if (!empty($branchIds)) {
            $query->whereIn('invoices.branch_id', $branchIds);
        }

        if (!empty($courseIds)) {
            $query->whereIn('classrooms.course_id', $courseIds);
        }

        return $query;
    }

    private function getRevenueTrend($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds)
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->whereBetween('payments.paid_at', [$startDate, $endDate]);

        $diffInDays = $startDate->diffInDays($endDate);

        if ($diffInDays <= 31) {
            // Daily grouping for ranges <= 31 days
            $results = $query
                ->selectRaw('DATE(payments.paid_at) as period, SUM(payments.amount) as revenue')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            return $results->map(function($item) {
                return [
                    'period' => Carbon::parse($item->period)->format('M j'),
                    'value' => (int) ($item->revenue ?? 0)
                ];
            });
        } else {
            // Monthly grouping for longer ranges
            $results = $query
                ->selectRaw('DATE_FORMAT(payments.paid_at, "%Y-%m") as period, SUM(payments.amount) as revenue')
                ->groupBy('period')
                ->orderBy('period')
                ->get();

            return $results->map(function($item) {
                return [
                    'period' => Carbon::createFromFormat('Y-m', $item->period)->format('M Y'),
                    'value' => (int) ($item->revenue ?? 0)
                ];
            });
        }
    }

    private function getInvoiceStatusByMonth($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds)
            ->whereBetween('invoices.created_at', [$startDate, $endDate]);

        $results = $query
            ->selectRaw('
                DATE_FORMAT(invoices.created_at, "%Y-%m") as month,
                invoices.status,
                COUNT(*) as count
            ')
            ->groupBy('month', 'invoices.status')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        return $results->map(function($monthData, $month) {
            $data = [
                'month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'),
                'paid' => 0,
                'unpaid' => 0,
                'partial' => 0,
                'refunded' => 0,
            ];

            foreach($monthData as $item) {
                $data[$item->status] = (int) $item->count;
            }

            return $data;
        })->values();
    }

    private function getRevenueByBranch($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds)
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->whereBetween('payments.paid_at', [$startDate, $endDate]);

        return $query
            ->selectRaw('branches.name, SUM(payments.amount) as revenue')
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'value' => (int) ($item->revenue ?? 0)
                ];
            });
    }

    private function getRevenueByCourse($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds)
            ->join('classrooms', 'invoices.class_id', '=', 'classrooms.id')
            ->join('courses', 'classrooms.course_id', '=', 'courses.id')
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->whereBetween('payments.paid_at', [$startDate, $endDate]);

        return $query
            ->selectRaw('courses.name, SUM(payments.amount) as revenue')
            ->groupBy('courses.id', 'courses.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->name,
                    'value' => (int) ($item->revenue ?? 0)
                ];
            });
    }

    private function getMonthlySummaryByBranch($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds)
            ->whereBetween('invoices.created_at', [$startDate, $endDate]);

        return $query
            ->selectRaw('
                DATE_FORMAT(invoices.created_at, "%Y-%m") as month,
                branches.name as branch_name,
                COUNT(*) as invoice_count,
                SUM(invoices.total) as total_amount,
                SUM(CASE WHEN invoices.status = "paid" THEN invoices.total ELSE 0 END) as paid_amount,
                SUM(CASE WHEN invoices.status = "unpaid" THEN invoices.total ELSE 0 END) as unpaid_amount,
                SUM(CASE WHEN invoices.status = "partial" THEN invoices.total ELSE 0 END) as partial_amount
            ')
            ->groupBy('month', 'branches.id', 'branches.name')
            ->orderBy('month')
            ->orderBy('branches.name')
            ->get()
            ->map(function($item) {
                return [
                    'month' => Carbon::createFromFormat('Y-m', $item->month)->format('M Y'),
                    'branch' => $item->branch_name,
                    'invoice_count' => (int) $item->invoice_count,
                    'total_amount' => (int) $item->total_amount,
                    'paid_amount' => (int) $item->paid_amount,
                    'unpaid_amount' => (int) $item->unpaid_amount,
                    'partial_amount' => (int) $item->partial_amount,
                ];
            });
    }
}

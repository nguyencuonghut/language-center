<?php

namespace App\Http\Controllers\Manager\Reports;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FinanceReportController extends Controller
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

        // Get detailed tables data
        $tables = $this->getTablesData($startDate, $endDate, $branchIds, $courseIds, $request);

        return Inertia::render('Manager/Reports/Finance', [
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
            'tables' => $tables,
        ]);
    }

    private function calculateKPIs($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds);

        // Revenue this month
        $revenueThisMonth = (clone $query)
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->whereBetween('payments.paid_at', [$startDate, $endDate])
            ->sum('payments.amount') ?? 0;

        // Unpaid invoices count
        $unpaidInvoicesCount = (clone $query)
            ->where('invoices.status', 'unpaid')
            ->count();

        // Collection rate (paid amount / total invoiced)
        $totalInvoiced = (clone $query)->sum('invoices.total') ?? 0;
        $totalPaid = (clone $query)
            ->join('payments', 'invoices.id', '=', 'payments.invoice_id')
            ->sum('payments.amount') ?? 0;

        $collectionRate = $totalInvoiced > 0 ? round(($totalPaid / $totalInvoiced) * 100, 1) : 0;

        // Outstanding amount
        $outstandingInvoices = (clone $query)
            ->whereIn('invoices.status', ['unpaid', 'partial'])
            ->select('invoices.id', 'invoices.total')
            ->get();

        $outstanding = 0;
        foreach ($outstandingInvoices as $invoice) {
            $paidAmount = Payment::where('invoice_id', $invoice->id)->sum('amount') ?? 0;
            $outstanding += ($invoice->total - $paidAmount);
        }

        return [
            'revenue_month' => ['total' => (int) $revenueThisMonth],
            'unpaid_invoices' => ['total' => $unpaidInvoicesCount],
            'collection_rate' => ['rate' => $collectionRate],
            'outstanding' => ['total' => (int) $outstanding],
        ];
    }

    private function getChartsData($startDate, $endDate, $branchIds, $courseIds)
    {
        // Daily/Monthly revenue trend
        $revenueTrend = $this->getRevenueTrend($startDate, $endDate, $branchIds, $courseIds);

        // Invoice status distribution
        $invoiceStatus = $this->getInvoiceStatusDistribution($branchIds, $courseIds);

        // Revenue by course
        $revenueByCourse = $this->getRevenueByCourse($startDate, $endDate, $branchIds, $courseIds);

        return [
            'revenue_trend' => $revenueTrend,
            'invoice_status' => $invoiceStatus,
            'revenue_by_course' => $revenueByCourse,
        ];
    }

    private function getTablesData($startDate, $endDate, $branchIds, $courseIds, $request)
    {
        // Invoice details
        $invoiceDetails = $this->getInvoiceDetails($startDate, $endDate, $branchIds, $courseIds);

        return [
            'invoice_details' => $invoiceDetails,
        ];
    }

    private function buildBaseQuery($branchIds, $courseIds = [])
    {
        $query = Invoice::query()
            ->join('students', 'invoices.student_id', '=', 'students.id')
            ->join('enrollments', 'students.id', '=', 'enrollments.student_id')
            ->join('classrooms', 'enrollments.class_id', '=', 'classrooms.id')
            ->whereIn('classrooms.branch_id', $branchIds);

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
                    'period' => Carbon::parse($item->period)->format('j/n'),
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

    private function getInvoiceStatusDistribution($branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds);

        $results = $query
            ->selectRaw('
                invoices.status,
                COUNT(*) as count,
                SUM(invoices.total) as total_amount
            ')
            ->groupBy('invoices.status')
            ->get();

        return $results->map(function($item) {
            $statusNames = [
                'paid' => 'Đã thu',
                'unpaid' => 'Chưa thu',
                'partial' => 'Thu một phần',
                'refunded' => 'Đã hoàn'
            ];

            return [
                'name' => $statusNames[$item->status] ?? ucfirst($item->status),
                'value' => (int) $item->count,
                'amount' => (int) $item->total_amount
            ];
        });
    }

    private function getRevenueByCourse($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds)
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

    private function getInvoiceDetails($startDate, $endDate, $branchIds, $courseIds)
    {
        $query = $this->buildBaseQuery($branchIds, $courseIds)
            ->whereBetween('invoices.created_at', [$startDate, $endDate]);

        return $query
            ->select([
                'invoices.id',
                'invoices.code',
                'students.code as student_code',
                'students.name as student_name',
                'classrooms.code as class_code',
                'classrooms.name as class_name',
                'invoices.total',
                'invoices.due_date',
                'invoices.status',
                'invoices.created_at'
            ])
            ->orderBy('invoices.created_at', 'desc')
            ->limit(100)
            ->get()
            ->map(function($item) {
                $statusNames = [
                    'paid' => 'Đã thu',
                    'unpaid' => 'Chưa thu',
                    'partial' => 'Thu một phần',
                    'refunded' => 'Đã hoàn'
                ];

                // Calculate paid amount from payments table
                $paidAmount = Payment::where('invoice_id', $item->id)->sum('amount') ?? 0;

                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'student' => "{$item->student_code} - {$item->student_name}",
                    'class' => "{$item->class_code} - {$item->class_name}",
                    'total' => (int) $item->total,
                    'paid_amount' => (int) $paidAmount,
                    'remaining' => (int) ($item->total - $paidAmount),
                    'due_date' => $item->due_date ? Carbon::parse($item->due_date)->format('d/m/Y') : null,
                    'status' => $statusNames[$item->status] ?? ucfirst($item->status),
                    'created_at' => Carbon::parse($item->created_at)->format('d/m/Y'),
                ];
            });
    }
}

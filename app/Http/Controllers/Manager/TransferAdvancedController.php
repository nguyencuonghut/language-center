<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\Student;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Inertia\Inertia;

class TransferAdvancedController extends Controller
{
    /**
     * Advanced search with multiple filters
     */
    public function search(Request $request)
    {
        $query = Transfer::with([
            'student',
            'fromClass',
            'toClass',
            'retargetedToClass',
            'createdBy',
            'lastModifiedBy'
        ]);

        // Apply filters
        $this->applyAdvancedFilters($query, $request);

        // Sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Default pagination with higher per_page to show more results initially
        $perPage = $request->get('per_page', 20);
        $transfers = $query->paginate($perPage)->withQueryString();

        return Inertia::render('Manager/Transfers/Advanced', [
            'transfers' => $transfers,
            'filters' => $request->only([
                'search', 'status', 'priority', 'source_system', 'created_by',
                'date_from', 'date_to', 'fee_min', 'fee_max', 'reason',
                'from_class', 'to_class', 'sort_field', 'sort_direction'
            ]),
            'filterOptions' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Transfer history for a specific student
     */
    public function history(Request $request, Student $student)
    {
        $transfers = Transfer::forStudent($student->id)
            ->with([
                'fromClass',
                'toClass',
                'retargetedToClass',
                'createdBy',
                'revertedBy',
                'retargetedBy',
                'lastModifiedBy'
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        $auditTrails = [];
        foreach ($transfers as $transfer) {
            $auditTrails[$transfer->id] = $transfer->getAuditTrail();
        }

        return Inertia::render('Manager/Transfers/History', [
            'student' => $student,
            'transfers' => $transfers,
            'auditTrails' => $auditTrails,
        ]);
    }

    /**
     * Enhanced reporting with custom date ranges and grouping
     */
    public function reports(Request $request)
    {
        $reportType = $request->get('report_type', 'summary');
        $dateFrom = $request->get('date_from', now()->startOfMonth());
        $dateTo = $request->get('date_to', now());
        $groupBy = $request->get('group_by', 'month');

        // Convert string dates if needed
        if (is_string($dateFrom)) {
            $dateFrom = Carbon::parse($dateFrom);
        }
        if (is_string($dateTo)) {
            $dateTo = Carbon::parse($dateTo);
        }

        $reportData = null;

        switch ($reportType) {
            case 'summary':
                $reportData = $this->getSummaryReport($dateFrom, $dateTo);
                break;
            case 'trends':
                $reportData = $this->getTrendsReport($dateFrom, $dateTo, $groupBy);
                break;
            case 'performance':
                $reportData = $this->getPerformanceReport($dateFrom, $dateTo);
                break;
            case 'detailed':
                $reportData = $this->getDetailedReport($request, $dateFrom, $dateTo);
                break;
        }

        return Inertia::render('Manager/Transfers/Reports', [
            'reportData' => $reportData,
            'filters' => $request->all(),
            'reportOptions' => [
                'reportTypes' => [
                    ['label' => 'Tổng quan', 'value' => 'summary'],
                    ['label' => 'Xu hướng', 'value' => 'trends'],
                    ['label' => 'Hiệu suất', 'value' => 'performance'],
                    ['label' => 'Chi tiết', 'value' => 'detailed']
                ],
                'groupOptions' => [
                    ['label' => 'Theo tháng', 'value' => 'month'],
                    ['label' => 'Theo tuần', 'value' => 'week'],
                    ['label' => 'Theo ngày', 'value' => 'day']
                ]
            ]
        ]);
    }

    /**
     * Export enhanced reports
     */
    public function exportReport(Request $request)
    {
        $reportType = $request->get('report_type', 'summary');
        $dateFrom = $request->get('date_from', now()->subMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        // Convert string dates if needed
        if (is_string($dateFrom)) {
            $dateFrom = Carbon::parse($dateFrom);
        }
        if (is_string($dateTo)) {
            $dateTo = Carbon::parse($dateTo);
        }

        $reportData = null;
        $filename = "transfer_report_{$reportType}_" . Carbon::parse($dateFrom)->format('Y-m-d') . '_to_' . Carbon::parse($dateTo)->format('Y-m-d') . '.csv';

        switch ($reportType) {
            case 'summary':
                return $this->exportSummary($dateFrom, $dateTo, $filename);
            case 'trends':
                return $this->exportTrends($dateFrom, $dateTo, $request->get('group_by', 'month'), $filename);
            case 'performance':
                return $this->exportPerformance($dateFrom, $dateTo, $filename);
            case 'detailed':
                return $this->exportDetailed($request, $dateFrom, $dateTo, $filename);
        }

        return response()->json(['error' => 'Invalid report type'], 400);
    }

    private function exportSummary($dateFrom, $dateTo, $filename)
    {
        $data = $this->getSummaryReport($dateFrom, $dateTo);

        $csv = "Báo cáo tổng quan chuyển lớp\n";
        $csv .= "Từ ngày: {$dateFrom->format('d/m/Y')}\n";
        $csv .= "Đến ngày: {$dateTo->format('d/m/Y')}\n\n";

        $csv .= "Chỉ số,Giá trị\n";
        $csv .= "Tổng chuyển lớp,{$data['totals']['total_transfers']}\n";
        $csv .= "Đang hoạt động,{$data['totals']['active_transfers']}\n";
        $csv .= "Đã hoàn tác,{$data['totals']['reverted_transfers']}\n";
        $csv .= "Tổng phí thu,{$data['totals']['total_fees']}\n";

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportDetailed($request, $dateFrom, $dateTo, $filename)
    {
        $data = $this->getDetailedReport($request, $dateFrom, $dateTo);

        $csv = "ID,Mã học viên,Tên học viên,Từ lớp,Đến lớp,Trạng thái,Phí chuyển,Lý do,Ngày tạo\n";

        foreach ($data['transfers'] as $transfer) {
            $csv .= "\"{$transfer['id']}\",";
            $csv .= "\"{$transfer['student']['code']}\",";
            $csv .= "\"{$transfer['student']['name']}\",";
            $csv .= "\"{$transfer['from_class']['code']}\",";
            $csv .= "\"{$transfer['to_class']['code']}\",";
            $csv .= "\"{$transfer['status']}\",";
            $csv .= "\"{$transfer['transfer_fee']}\",";
            $csv .= "\"{$transfer['reason']}\",";
            $csv .= "\"" . Carbon::parse($transfer['created_at'])->format('d/m/Y H:i') . "\"\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportTrends($dateFrom, $dateTo, $groupBy, $filename)
    {
        $data = $this->getTrendsReport($dateFrom, $dateTo, $groupBy);

        $csv = "Kỳ,Tổng,Hoạt động,Hoàn tác,Ưu tiên,Tổng phí\n";

        foreach ($data['trends'] as $trend) {
            $csv .= "\"{$trend['period']}\",";
            $csv .= "{$trend['total']},";
            $csv .= "{$trend['active']},";
            $csv .= "{$trend['reverted']},";
            $csv .= "{$trend['priority']},";
            $csv .= "{$trend['total_fees']}\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    private function exportPerformance($dateFrom, $dateTo, $filename)
    {
        $data = $this->getPerformanceReport($dateFrom, $dateTo);

        $csv = "Người dùng,Tổng transfers,Thành công,Tỷ lệ thành công (%)\n";

        foreach ($data['user_performance'] as $user) {
            $csv .= "\"{$user['name']}\",";
            $csv .= "{$user['total_transfers']},";
            $csv .= "{$user['successful']},";
            $csv .= "{$user['success_rate']}\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    /**
     * Apply advanced filters to query
     */
    private function applyAdvancedFilters($query, Request $request): void
    {
        // Text search
        if ($search = $request->get('search')) {
            $query->whereHas('student', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            })->orWhere('reason', 'like', "%{$search}%")
              ->orWhere('notes', 'like', "%{$search}%")
              ->orWhere('admin_notes', 'like', "%{$search}%");
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        // Priority filter
        if ($request->has('priority') && $request->get('priority') !== null && $request->get('priority') !== '') {
            $priority = $request->get('priority');
            
            // Handle string boolean values from frontend
            if ($priority === 'true' || $priority === true) {
                $query->where('is_priority', true);
            } elseif ($priority === 'false' || $priority === false) {
                $query->where('is_priority', false);
            }
        }

        // Source system filter
        if ($sourceSystem = $request->get('source_system')) {
            $query->where('source_system', $sourceSystem);
        }

        // Created by filter
        if ($createdBy = $request->get('created_by')) {
            $query->where('created_by', $createdBy);
        }

        // Date range filter
        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Fee range filter
        if ($feeMin = $request->get('fee_min')) {
            $query->where('transfer_fee', '>=', $feeMin);
        }
        if ($feeMax = $request->get('fee_max')) {
            $query->where('transfer_fee', '<=', $feeMax);
        }

        // Reason filter
        if ($reason = $request->get('reason')) {
            $query->where('reason', 'like', "%{$reason}%");
        }

        // Class filters
        if ($fromClass = $request->get('from_class')) {
            $query->where('from_class_id', $fromClass);
        }
        if ($toClass = $request->get('to_class')) {
            $query->where('to_class_id', $toClass);
        }
    }

    /**
     * Get filter options for dropdowns
     */
    private function getFilterOptions(): array
    {
        return [
            'statuses' => [
                ['label' => 'Tất cả', 'value' => ''],
                ['label' => 'Đang hoạt động', 'value' => 'active'],
                ['label' => 'Đã hoàn tác', 'value' => 'reverted'],
                ['label' => 'Đã đổi hướng', 'value' => 'retargeted'],
            ],
            'sources' => [
                ['label' => 'Thủ công', 'value' => 'manual'],
                ['label' => 'Hệ thống', 'value' => 'system'],
                ['label' => 'Import', 'value' => 'import'],
            ],
            'creators' => User::role(['admin', 'manager'])
                ->select('id', 'name')
                ->orderBy('name')
                ->get()
                ->map(fn($user) => ['label' => $user->name, 'value' => $user->id]),
            'classes' => Classroom::select('id', 'code', 'name')
                ->orderBy('code')
                ->get()
                ->map(fn($class) => ['label' => "{$class->code} - {$class->name}", 'value' => $class->id]),
            'commonReasons' => [
                'Xin chuyển lịch học phù hợp hơn',
                'Chuyển gần nhà hơn',
                'Theo bạn bè',
                'Thay đổi nhu cầu học tập',
                'Chuyển chi nhánh gần hơn',
            ]
        ];
    }

    /**
     * Get report options
     */
    private function getReportOptions(): array
    {
        return [
            'reportTypes' => [
                ['label' => 'Tổng quan', 'value' => 'summary'],
                ['label' => 'Chi tiết', 'value' => 'detailed'],
                ['label' => 'Xu hướng', 'value' => 'trends'],
                ['label' => 'Hiệu suất', 'value' => 'performance'],
            ],
            'groupOptions' => [
                ['label' => 'Theo ngày', 'value' => 'day'],
                ['label' => 'Theo tuần', 'value' => 'week'],
                ['label' => 'Theo tháng', 'value' => 'month'],
                ['label' => 'Theo quý', 'value' => 'quarter'],
            ]
        ];
    }

    /**
     * Generate summary report
     */
    private function getSummaryReport(string $dateFrom, string $dateTo): array
    {
        $baseQuery = Transfer::whereBetween('created_at', [$dateFrom, $dateTo]);

        return [
            'totals' => [
                'total_transfers' => $baseQuery->count(),
                'active_transfers' => $baseQuery->where('status', 'active')->count(),
                'reverted_transfers' => $baseQuery->where('status', 'reverted')->count(),
                'retargeted_transfers' => $baseQuery->where('status', 'retargeted')->count(),
                'priority_transfers' => $baseQuery->where('is_priority', true)->count(),
                'total_fees' => $baseQuery->sum('transfer_fee'),
                'average_fee' => $baseQuery->avg('transfer_fee'),
            ],
            'by_source' => $baseQuery->select('source_system', DB::raw('COUNT(*) as count'))
                ->groupBy('source_system')
                ->get(),
            'top_reasons' => $baseQuery->select('reason', DB::raw('COUNT(*) as count'))
                ->whereNotNull('reason')
                ->groupBy('reason')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),
        ];
    }

    /**
     * Generate detailed report
     */
    private function getDetailedReport(string $dateFrom, string $dateTo, Request $request): array
    {
        $query = Transfer::with(['student', 'fromClass', 'toClass', 'createdBy'])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $this->applyAdvancedFilters($query, $request);

        return [
            'transfers' => $query->orderBy('created_at', 'desc')->get(),
            'summary' => $this->getSummaryReport($dateFrom, $dateTo)
        ];
    }

    /**
     * Generate trends report
     */
    private function getTrendsReport(string $dateFrom, string $dateTo, string $groupBy): array
    {
        $dateFormat = match($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-Week %u',
            'quarter' => '%Y-Q%q',
            default => '%Y-%m'
        };

        $trends = Transfer::whereBetween('created_at', [$dateFrom, $dateTo])
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
                DB::raw('COUNT(*) as total'),
                DB::raw('COUNT(CASE WHEN status = "active" THEN 1 END) as active'),
                DB::raw('COUNT(CASE WHEN status = "reverted" THEN 1 END) as reverted'),
                DB::raw('COUNT(CASE WHEN is_priority = 1 THEN 1 END) as priority'),
                DB::raw('SUM(transfer_fee) as total_fees')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'trends' => $trends,
            'chart_data' => [
                'labels' => $trends->pluck('period'),
                'datasets' => [
                    [
                        'label' => 'Tổng chuyển lớp',
                        'data' => $trends->pluck('total'),
                        'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    ],
                    [
                        'label' => 'Đang hoạt động',
                        'data' => $trends->pluck('active'),
                        'backgroundColor' => 'rgba(16, 185, 129, 0.5)',
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate performance report
     */
    private function getPerformanceReport(string $dateFrom, string $dateTo): array
    {
        return [
            'success_rate' => $this->getSuccessRateByPeriod($dateFrom, $dateTo),
            'processing_time' => $this->getProcessingTimeStats($dateFrom, $dateTo),
            'user_performance' => $this->getUserPerformanceStats($dateFrom, $dateTo),
            'class_performance' => $this->getClassPerformanceStats($dateFrom, $dateTo),
        ];
    }

    // Additional helper methods for reports...
    private function getSuccessRateByPeriod(string $dateFrom, string $dateTo): array
    {
        $total = Transfer::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $successful = Transfer::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'active')
            ->count();

        return [
            'total' => $total,
            'successful' => $successful,
            'rate' => $total > 0 ? round(($successful / $total) * 100, 2) : 0
        ];
    }

    private function getProcessingTimeStats(string $dateFrom, string $dateTo): array
    {
        $stats = Transfer::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('processed_at')
            ->selectRaw('
                AVG(TIMESTAMPDIFF(HOUR, created_at, processed_at)) as avg_hours,
                MIN(TIMESTAMPDIFF(HOUR, created_at, processed_at)) as min_hours,
                MAX(TIMESTAMPDIFF(HOUR, created_at, processed_at)) as max_hours
            ')
            ->first();

        return [
            'average_hours' => round($stats->avg_hours ?? 0, 2),
            'min_hours' => $stats->min_hours ?? 0,
            'max_hours' => $stats->max_hours ?? 0,
        ];
    }

    private function getUserPerformanceStats(string $dateFrom, string $dateTo): array
    {
        return Transfer::whereBetween('created_at', [$dateFrom, $dateTo])
            ->join('users', 'transfers.created_by', '=', 'users.id')
            ->select(
                'users.name',
                DB::raw('COUNT(*) as total_transfers'),
                DB::raw('COUNT(CASE WHEN status = "active" THEN 1 END) as successful'),
                DB::raw('ROUND(COUNT(CASE WHEN status = "active" THEN 1 END) / COUNT(*) * 100, 2) as success_rate')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_transfers')
            ->limit(10)
            ->get();
    }

    private function getClassPerformanceStats(string $dateFrom, string $dateTo): array
    {
        return Transfer::whereBetween('created_at', [$dateFrom, $dateTo])
            ->join('classrooms as from_class', 'transfers.from_class_id', '=', 'from_class.id')
            ->select(
                'from_class.code as class_code',
                'from_class.name as class_name',
                DB::raw('COUNT(*) as transfers_out'),
                DB::raw('COUNT(CASE WHEN status = "reverted" THEN 1 END) as reverted')
            )
            ->groupBy('from_class.id', 'from_class.code', 'from_class.name')
            ->orderByDesc('transfers_out')
            ->limit(10)
            ->get();
    }

    /**
     * Generate CSV response
     */
    private function generateCsvResponse(array $data, string $filename)
    {
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($data) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            // Write headers and data based on report type
            foreach ($data as $row) {
                fputcsv($file, $row);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // Export helper methods...
    private function getDetailedReportForExport(string $dateFrom, string $dateTo, Request $request): array
    {
        $query = Transfer::with(['student', 'fromClass', 'toClass', 'createdBy'])
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $this->applyAdvancedFilters($query, $request);

        $transfers = $query->orderBy('created_at', 'desc')->get();

        $data = [
            ['ID', 'Học viên', 'Mã HV', 'Từ lớp', 'Đến lớp', 'Ngày hiệu lực', 'Lý do', 'Trạng thái', 'Ưu tiên', 'Phí', 'Nguồn', 'Người tạo', 'Ngày tạo']
        ];

        foreach ($transfers as $transfer) {
            $data[] = [
                $transfer->id,
                $transfer->student->name ?? 'N/A',
                $transfer->student->code ?? 'N/A',
                $transfer->fromClass->code ?? 'N/A',
                $transfer->toClass->code ?? 'N/A',
                $transfer->effective_date,
                $transfer->reason,
                $transfer->status,
                $transfer->is_priority ? 'Có' : 'Không',
                number_format($transfer->transfer_fee, 0, ',', '.') . ' VND',
                $transfer->source_system,
                $transfer->createdBy->name ?? 'N/A',
                $transfer->created_at->format('d/m/Y H:i')
            ];
        }

        return $data;
    }

    private function getAuditReportForExport(string $dateFrom, string $dateTo): array
    {
        $transfers = Transfer::whereBetween('created_at', [$dateFrom, $dateTo])
            ->with(['student', 'createdBy', 'lastModifiedBy'])
            ->get();

        $data = [
            ['Transfer ID', 'Học viên', 'Hành động', 'Người thực hiện', 'Thời gian', 'Chi tiết']
        ];

        foreach ($transfers as $transfer) {
            $auditTrail = $transfer->getAuditTrail();
            foreach ($auditTrail as $entry) {
                $data[] = [
                    $transfer->id,
                    $transfer->student->name ?? 'N/A',
                    $entry['description'],
                    $entry['user']->name ?? 'System',
                    $entry['timestamp'],
                    json_encode($entry['details'] ?? [])
                ];
            }
        }

        return $data;
    }

    private function getBasicReportForExport(string $dateFrom, string $dateTo): array
    {
        $summary = $this->getSummaryReport($dateFrom, $dateTo);

        $data = [
            ['Metric', 'Value'],
            ['Tổng chuyển lớp', $summary['totals']['total_transfers']],
            ['Đang hoạt động', $summary['totals']['active_transfers']],
            ['Đã hoàn tác', $summary['totals']['reverted_transfers']],
            ['Đã đổi hướng', $summary['totals']['retargeted_transfers']],
            ['Ưu tiên', $summary['totals']['priority_transfers']],
            ['Tổng phí', number_format($summary['totals']['total_fees'], 0, ',', '.') . ' VND'],
            ['Phí trung bình', number_format($summary['totals']['average_fee'], 0, ',', '.') . ' VND'],
        ];

        return $data;
    }
}

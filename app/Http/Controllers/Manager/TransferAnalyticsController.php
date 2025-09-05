<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Transfer;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class TransferAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'month');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        // Determine date range based on period
        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        // Get analytics data
        $analytics = [
            'total_transfers' => $this->getTotalTransfers($start, $end),
            'success_rate' => $this->getSuccessRate($start, $end),
            'revert_rate' => $this->getRevertRate($start, $end),
            'total_fee' => $this->getTotalFee($start, $end),
            'avg_processing_days' => $this->getAvgProcessingDays($start, $end),
            'chart_data' => $this->getChartData($start, $end, $period),
            'status_breakdown' => $this->getStatusBreakdown($start, $end),
            'top_classes' => $this->getTopClasses($start, $end),
            'reasons_analysis' => $this->getReasonsAnalysis($start, $end),
            'by_branch' => $this->getByBranch($start, $end),
            'by_teacher' => $this->getByTeacher($start, $end),
            'operators_activity' => $this->getOperatorsActivity($start, $end),
        ];

        return Inertia::render('Manager/Transfers/Analytics', [
            'analytics' => $analytics,
            'filters' => [
                'period' => $period,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]
        ]);
    }

    public function export(Request $request)
    {
        $period = $request->get('period', 'month');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        [$start, $end] = $this->getDateRange($period, $startDate, $endDate);

        // Get detailed data for export
        $transfers = Transfer::with(['student', 'fromClass', 'toClass', 'createdBy'])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at', 'desc')
            ->get();

        $filename = 'transfer_report_' . $start->format('Y-m-d') . '_to_' . $end->format('Y-m-d') . '.csv';

        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$filename}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function() use ($transfers) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM for Excel compatibility
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header row
            fputcsv($file, [
                'ID',
                'Học viên',
                'Mã học viên',
                'Từ lớp',
                'Đến lớp',
                'Ngày hiệu lực',
                'Lý do',
                'Trạng thái',
                'Phí chuyển lớp',
                'Người tạo',
                'Ngày tạo'
            ]);

            // Data rows
            foreach ($transfers as $transfer) {
                fputcsv($file, [
                    $transfer->id,
                    $transfer->student->name,
                    $transfer->student->code,
                    $transfer->fromClass->code,
                    $transfer->toClass->code,
                    $transfer->effective_date,
                    $transfer->reason,
                    $this->getStatusLabel($transfer->status),
                    number_format($transfer->transfer_fee, 0, ',', '.') . ' VND',
                    $transfer->createdBy->name ?? 'N/A',
                    $transfer->created_at->format('d/m/Y H:i')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function getDateRange($period, $startDate, $endDate)
    {
        $now = Carbon::now();

        switch ($period) {
            case 'week':
                return [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek()
                ];
            case 'quarter':
                return [
                    $now->copy()->startOfQuarter(),
                    $now->copy()->endOfQuarter()
                ];
            case 'year':
                return [
                    $now->copy()->startOfYear(),
                    $now->copy()->endOfYear()
                ];
            case 'custom':
                if ($startDate && $endDate) {
                    return [Carbon::parse($startDate), Carbon::parse($endDate)];
                }
                // fallback to month
            case 'month':
            default:
                return [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth()
                ];
        }
    }

    private function getTotalTransfers($start, $end)
    {
        return Transfer::whereBetween('created_at', [$start, $end])->count();
    }

    private function getSuccessRate($start, $end)
    {
        $total = Transfer::whereBetween('created_at', [$start, $end])->count();
        if ($total === 0) return 0;

        $successful = Transfer::whereBetween('created_at', [$start, $end])
            ->where('status', 'active')
            ->count();

        return round(($successful / $total) * 100, 1);
    }

    private function getTotalFee($start, $end)
    {
        return Transfer::whereBetween('created_at', [$start, $end])
            ->sum('transfer_fee');
    }

    private function getAvgProcessingDays($start, $end)
    {
        $transfers = Transfer::whereBetween('created_at', [$start, $end])
            ->whereNotNull('processed_at')
            ->select(DB::raw('AVG(DATEDIFF(processed_at, created_at)) as avg_days'))
            ->first();

        return round($transfers->avg_days ?? 0, 1);
    }

    private function getChartData($start, $end, $period)
    {
        $dateFormat = match($period) {
            'week' => '%Y-%m-%d',
            'year' => '%Y-%m',
            'quarter' => '%Y-%m',
            default => '%Y-%m-%d'
        };

        $groupFormat = match($period) {
            'week' => 'DATE(created_at)',
            'year' => 'DATE_FORMAT(created_at, "%Y-%m")',
            'quarter' => 'DATE_FORMAT(created_at, "%Y-%m")',
            default => 'DATE(created_at)'
        };

        $data = Transfer::whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw("{$groupFormat} as date"),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date')->map(function($date) use ($period) {
                return match($period) {
                    'year', 'quarter' => Carbon::createFromFormat('Y-m', $date)->format('M Y'),
                    default => Carbon::parse($date)->format('d/m')
                };
            })->toArray(),
            'data' => $data->pluck('count')->toArray()
        ];
    }

    private function getStatusBreakdown($start, $end)
    {
        $breakdown = Transfer::whereBetween('created_at', [$start, $end])
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'active' => $breakdown['active'] ?? 0,
            'reverted' => $breakdown['reverted'] ?? 0,
            'retargeted' => $breakdown['retargeted'] ?? 0,
        ];
    }

    private function getTopClasses($start, $end)
    {
        // Get classes with transfer in/out counts using a simpler approach
        $classes = DB::table('classrooms')
            ->leftJoin('transfers as transfers_out', function($join) use ($start, $end) {
                $join->on('classrooms.id', '=', 'transfers_out.from_class_id')
                     ->whereBetween('transfers_out.created_at', [$start, $end]);
            })
            ->leftJoin('transfers as transfers_in', function($join) use ($start, $end) {
                $join->on('classrooms.id', '=', 'transfers_in.to_class_id')
                     ->whereBetween('transfers_in.created_at', [$start, $end]);
            })
            ->select(
                'classrooms.code as class_code',
                'classrooms.name as class_name',
                DB::raw('COUNT(DISTINCT transfers_out.id) as transfers_out'),
                DB::raw('COUNT(DISTINCT transfers_in.id) as transfers_in'),
                DB::raw('COUNT(DISTINCT transfers_in.id) - COUNT(DISTINCT transfers_out.id) as net_change')
            )
            ->groupBy('classrooms.id', 'classrooms.code', 'classrooms.name')
            ->having(DB::raw('COUNT(DISTINCT transfers_out.id) + COUNT(DISTINCT transfers_in.id)'), '>', 0)
            ->orderByDesc(DB::raw('COUNT(DISTINCT transfers_out.id) + COUNT(DISTINCT transfers_in.id)'))
            ->limit(10)
            ->get();

        return $classes->toArray();
    }

    private function getReasonsAnalysis($start, $end)
    {
        return Transfer::whereBetween('created_at', [$start, $end])
            ->whereNotNull('reason')
            ->select('reason', DB::raw('COUNT(*) as count'))
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->toArray();
    }

    private function getStatusLabel($status)
    {
        return match($status) {
            'active' => 'Đang hoạt động',
            'reverted' => 'Đã hoàn tác',
            'retargeted' => 'Đã đổi hướng',
            default => $status
        };
    }

    /**
     * Get revert rate percentage
     */
    private function getRevertRate($start, $end)
    {
        $total = Transfer::whereBetween('created_at', [$start, $end])->count();
        $reverted = Transfer::whereBetween('created_at', [$start, $end])
            ->where('status', 'reverted')
            ->count();

        return $total > 0 ? round(($reverted / $total) * 100, 2) : 0;
    }

    /**
     * Get transfers breakdown by branch
     */
    private function getByBranch($start, $end)
    {
        return Transfer::whereBetween('transfers.created_at', [$start, $end])
            ->join('classrooms as from_class', 'transfers.from_class_id', '=', 'from_class.id')
            ->join('branches', 'from_class.branch_id', '=', 'branches.id')
            ->select([
                'branches.id',
                'branches.name',
                DB::raw('COUNT(*) as total_transfers'),
                DB::raw('SUM(CASE WHEN transfers.status = "active" THEN 1 ELSE 0 END) as active'),
                DB::raw('SUM(CASE WHEN transfers.status = "reverted" THEN 1 ELSE 0 END) as reverted'),
                DB::raw('SUM(CASE WHEN transfers.status = "retargeted" THEN 1 ELSE 0 END) as retargeted'),
                DB::raw('SUM(transfers.transfer_fee) as total_fees')
            ])
            ->groupBy('branches.id', 'branches.name')
            ->orderByDesc('total_transfers')
            ->get()
            ->toArray();
    }

    /**
     * Get transfers breakdown by teacher
     */
    private function getByTeacher($start, $end)
    {
        return Transfer::whereBetween('transfers.created_at', [$start, $end])
            ->join('classrooms as from_class', 'transfers.from_class_id', '=', 'from_class.id')
            ->join('teaching_assignments', 'from_class.id', '=', 'teaching_assignments.class_id')
            ->join('users as teachers', 'teaching_assignments.teacher_id', '=', 'teachers.id')
            ->select([
                'teachers.id',
                'teachers.name as teacher_name',
                DB::raw('COUNT(DISTINCT transfers.id) as total_transfers'),
                DB::raw('SUM(CASE WHEN transfers.status = "active" THEN 1 ELSE 0 END) as active'),
                DB::raw('SUM(CASE WHEN transfers.status = "reverted" THEN 1 ELSE 0 END) as reverted'),
                DB::raw('SUM(CASE WHEN transfers.status = "retargeted" THEN 1 ELSE 0 END) as retargeted'),
                DB::raw('SUM(transfers.transfer_fee) as total_fees')
            ])
            ->groupBy('teachers.id', 'teachers.name')
            ->orderByDesc('total_transfers')
            ->limit(20) // Top 20 teachers
            ->get()
            ->toArray();
    }

    /**
     * Get operators activity (who performs transfers/reverts/retargets)
     */
    private function getOperatorsActivity($start, $end)
    {
        $data = [];

        // Created transfers
        $created = Transfer::whereBetween('transfers.created_at', [$start, $end])
            ->join('users', 'transfers.created_by', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as created_count')
            ])
            ->groupBy('users.id', 'users.name')
            ->get();

        // Reverted transfers
        $reverted = Transfer::whereBetween('reverted_at', [$start, $end])
            ->whereNotNull('reverted_by')
            ->join('users', 'transfers.reverted_by', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as reverted_count')
            ])
            ->groupBy('users.id', 'users.name')
            ->get();

        // Retargeted transfers
        $retargeted = Transfer::whereBetween('retargeted_at', [$start, $end])
            ->whereNotNull('retargeted_by')
            ->join('users', 'transfers.retargeted_by', '=', 'users.id')
            ->select([
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as retargeted_count')
            ])
            ->groupBy('users.id', 'users.name')
            ->get();

        // Merge data by user
        $users = [];

        foreach ($created as $user) {
            $users[$user->id] = [
                'id' => $user->id,
                'name' => $user->name,
                'created' => $user->created_count,
                'reverted' => 0,
                'retargeted' => 0,
                'total' => $user->created_count
            ];
        }

        foreach ($reverted as $user) {
            if (isset($users[$user->id])) {
                $users[$user->id]['reverted'] = $user->reverted_count;
                $users[$user->id]['total'] += $user->reverted_count;
            } else {
                $users[$user->id] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'created' => 0,
                    'reverted' => $user->reverted_count,
                    'retargeted' => 0,
                    'total' => $user->reverted_count
                ];
            }
        }

        foreach ($retargeted as $user) {
            if (isset($users[$user->id])) {
                $users[$user->id]['retargeted'] = $user->retargeted_count;
                $users[$user->id]['total'] += $user->retargeted_count;
            } else {
                $users[$user->id] = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'created' => 0,
                    'reverted' => 0,
                    'retargeted' => $user->retargeted_count,
                    'total' => $user->retargeted_count
                ];
            }
        }

        // Sort by total and return
        uasort($users, function($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return array_values($users);
    }
}

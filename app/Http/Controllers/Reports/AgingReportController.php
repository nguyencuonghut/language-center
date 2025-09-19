<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\StudentLedgerEntry;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class AgingReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $context = $request->routeIs('admin.*') ? 'admin' : 'manager';

        // --- Parse filters chịu lỗi ---
        $branchId = $this->parseBranchId($request->input('branch_id')); // null nếu '', 0, '0'
        [$dateFrom, $dateTo] = $this->parseDateRange(
            $request->input('date_from'),
            $request->input('date_to')
        );
        $branchId = $request->integer('branch_id');
        $dateFrom = $request->date('date_from');
        $dateTo   = $request->date('date_to');

        // --- Build query base ---
        $base = StudentLedgerEntry::query();

        // Scope theo vai trò (tùy hệ thống, có thể bỏ nếu chưa có quan hệ branch)
        if ($context === 'manager') {
            // Giới hạn theo chi nhánh được quản lý
            $managed = $request->user()->managerBranches()->pluck('branches.id');
            $base->whereHas('student.enrollments.classroom', function ($q) use ($managed) {
                $q->whereIn('branch_id', $managed);
            });
        }
        if ($branchId) {
            $base->whereHas('student.enrollments.classroom', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }
        if ($dateFrom) $base->whereDate('entry_date', '>=', $dateFrom);
        if ($dateTo)   $base->whereDate('entry_date', '<=', $dateTo);

        // Tổng hợp theo học viên
        $perStudent = (clone $base)
            ->select(
                'student_id',
                DB::raw('SUM(debit) AS t_debit'),
                DB::raw('SUM(credit) AS t_credit'),
                // Tuổi nợ dựa trên dòng debit (invoice) mới nhất còn trong khoảng lọc
                DB::raw("MAX(CASE WHEN type='invoice' THEN entry_date END) AS last_invoice_date")
            )
            ->groupBy('student_id')
            ->get();

        $now = now();

        // Chuẩn hóa rows chi tiết cho bảng
        $rows = $perStudent->map(function ($r) use ($now) {
            $balance = (float)$r->t_debit - (float)$r->t_credit;
            $days = $r->last_invoice_date ? $now->diffInDays($r->last_invoice_date) : 0;
            $bucket = match (true) {
                $days <= 30  => '0-30',
                $days <= 60  => '31-60',
                $days <= 90  => '61-90',
                default      => '90+',
            };
            return [
                'student_id'   => $r->student_id,
                'student_code' => optional(Student::find($r->student_id))->code,
                'student_name' => optional(Student::find($r->student_id))->name,
                'balance'      => $balance,
                'days'         => $days,
                'bucket'       => $bucket,
            ];
        });

        // KPI
        $totalDebit  = (clone $base)->sum('debit');
        $totalCredit = (clone $base)->sum('credit');
        $netBalance  = (float)$totalDebit - (float)$totalCredit;

        $totalPositive = $rows->where('balance', '>', 0)->sum('balance'); // tổng còn nợ
        $totalNegative = abs($rows->where('balance', '<', 0)->sum('balance')); // tổng nộp dư

        $kpi = [
            'total_debit'     => (float)$totalDebit,
            'total_credit'    => (float)$totalCredit,
            'net_balance'     => (float)$netBalance,
            'total_outstanding' => (float)$totalPositive,
            'total_overpaid'    => (float)$totalNegative,
        ];

        // Charts
        // 1) Pie theo bucket (gộp theo số lượng học viên có nợ dương, chỉ tính balance dương để thể hiện công nợ)
        $bucketGroups = $rows->filter(fn($r) => $r['balance'] > 0)->groupBy('bucket');
        $agingPie = [
            'labels' => ['0-30','31-60','61-90','90+'],
            'values' => [
                (float)($bucketGroups->get('0-30')?->sum('balance') ?? 0),
                (float)($bucketGroups->get('31-60')?->sum('balance') ?? 0),
                (float)($bucketGroups->get('61-90')?->sum('balance') ?? 0),
                (float)($bucketGroups->get('90+')?->sum('balance') ?? 0),
            ],
        ];

        // 2) Cột: tổng Debit vs Credit trong khoảng lọc (để cảm nhận thu/chi)
        $barDebitCredit = [
            'labels' => ['Phải thu (debit)','Đã thu (credit)'],
            'values' => [(float)$totalDebit, (float)$totalCredit],
        ];

        // Tables
        $tables = [
            'details' => $rows->values(),
        ];

        // Tuỳ chọn filters
        $branches = \App\Models\Branch::query()
            ->select('id','name')
            ->orderBy('name')
            ->get()
            ->values();
        // Thêm option "Tất cả"
        //array_unshift($branches, ['id' => null, 'name' => 'Tất cả']);

        $filterOptions = [
            'branches' => $branches, // [{id,name}] nếu cần
        ];

        return inertia('Reports/Aging', [
            'context'       => $context,
            'filters'       => [
                'branch_id' => $branchId,
                'date_from' => $dateFrom?->toDateString(),
                'date_to'   => $dateTo?->toDateString(),
            ],
            'filterOptions' => $filterOptions,
            'kpi'           => $kpi,
            'charts'        => [
                'aging_pie'        => $agingPie,
                'bar_debit_credit' => $barDebitCredit,
            ],
            'tables'        => $tables,
        ]);
    }


    /** '' | 0 | '0' | null -> null; số dương -> int */
    private function parseBranchId($raw): ?int
    {
        if ($raw === null) return null;
        $v = (int) $raw;
        return $v > 0 ? $v : null;
    }

    /**
     * Chấp nhận các định dạng: 'YYYY-MM-DD', 'DD/MM/YYYY', ISO date, Date string FE gửi lên.
     * Nếu trống -> default 30 ngày gần nhất.
     */
    private function parseDateRange($from, $to): array
    {
        $parse = function ($s): ?Carbon {
            if (!$s) return null;
            if ($s instanceof Carbon) return $s;
            $str = (string)$s;

            // Thử DD/MM/YYYY
            if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $str)) {
                return Carbon::createFromFormat('d/m/Y', $str)->startOfDay();
            }

            // Thử YYYY-MM-DD hoặc ISO
            try {
                return Carbon::parse($str)->startOfDay();
            } catch (\Throwable $e) {
                return null;
            }
        };

        $df = $parse($from);
        $dt = $parse($to);

        if (!$df && !$dt) {
            // default 30 ngày gần nhất
            $dt = Carbon::now()->endOfDay();
            $df = (clone $dt)->subDays(30)->startOfDay();
        } elseif ($df && !$dt) {
            $dt = Carbon::now()->endOfDay();
        } elseif (!$df && $dt) {
            $df = (clone $dt)->subDays(30)->startOfDay();
        }

        return [$df, $dt];
    }
}

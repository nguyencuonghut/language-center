<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollRequest;
use App\Http\Requests\Payroll\ApprovePayrollRequest;
use App\Http\Requests\Payroll\LockPayrollRequest;
use App\Models\Branch;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\TeacherTimesheet;
use App\Models\ClassSession;
use App\Models\Classes; // nếu model của bạn là Classroom => sửa lại import
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Str;

class PayrollController extends Controller
{
    /**
     * Danh sách kỳ lương.
     * Filters: branch, status, from, to, per_page
     */
    public function index(Request $request)
    {
        $perPage = (int)($request->integer('per_page') ?: 12);

        $q = Payroll::query()
            ->with(['branch:id,name', 'approver:id,name'])
            ->when($request->filled('branch') && $request->branch !== 'all', function ($qq) use ($request) {
                $qq->where('branch_id', (int)$request->branch);
            })
            ->when($request->filled('status') && in_array($request->status, ['draft','approved','locked'], true), function ($qq) use ($request) {
                $qq->where('status', $request->status);
            })
            ->when($request->filled('from'), fn($qq) => $qq->whereDate('period_from', '>=', $request->get('from')))
            ->when($request->filled('to'),   fn($qq) => $qq->whereDate('period_to',   '<=', $request->get('to')))
            ->orderByDesc('id');

        $payrolls = $q->paginate($perPage)->withQueryString();

        $branches = Branch::select('id','name')->orderBy('name')->get();

        return Inertia::render('Manager/Payrolls/Index', [
            'payrolls' => $payrolls,
            'branches' => $branches,
            'filters'  => [
                'branch'  => $request->get('branch', 'all'),
                'status'  => $request->get('status'),
                'from'    => $request->get('from'),
                'to'      => $request->get('to'),
                'perPage' => $perPage,
            ],
        ]);
    }

    /**
     * Form tạo kỳ lương (chọn chi nhánh + khoảng ngày).
     */
    public function create(Request $request)
    {
        $branches = Branch::select('id','name')->orderBy('name')->get();

        // Gợi ý khoảng ngày: từ đầu tháng đến hôm nay
        $from = now()->startOfMonth()->toDateString();
        $to   = now()->toDateString();

        return Inertia::render('Manager/Payrolls/Create', [
            'branches' => $branches,
            'defaults' => [
                'branch_id'   => null,
                'period_from' => $from,
                'period_to'   => $to,
            ],
        ]);
    }

    /**
     * Generate kỳ lương từ Timesheets APPROVED trong khoảng ngày (không trùng payroll trước).
     * - Không include timesheets đã nằm trong payroll_items khác.
     */
    public function store(StorePayrollRequest $request)
    {
        $data = $request->validated();

        $branchId   = $data['branch_id'] ?? null;
        $periodFrom = $data['period_from'];
        $periodTo   = $data['period_to'];

        // Lấy timesheets được duyệt trong khoảng, không trùng payroll
        // Join class_sessions để lọc theo ngày & chi nhánh (qua classrooms)
        $tsQuery = TeacherTimesheet::query()
            ->select('teacher_timesheets.*')
            ->join('class_sessions','class_sessions.id','=','teacher_timesheets.class_session_id')
            ->join('classrooms','classrooms.id','=','class_sessions.class_id')
            ->leftJoin('payroll_items','payroll_items.teacher_timesheet_id','=','teacher_timesheets.id')
            ->whereNull('payroll_items.id')
            ->where('teacher_timesheets.status', 'approved')
            ->whereBetween('class_sessions.date', [$periodFrom, $periodTo])
            ->when($branchId, fn($qq) => $qq->where('classrooms.branch_id', $branchId));

        $timesheets = $tsQuery->with([
            'session:id,class_id,date,start_time,end_time,room_id',
            'teacher:id,name',
        ])->get();

        if ($timesheets->isEmpty()) {
            return back()->with('error', 'Không có chấm công đã duyệt trong khoảng ngày (hoặc đã được tính lương).');
        }

        DB::transaction(function () use ($timesheets, $branchId, $periodFrom, $periodTo, $request) {
            // Tạo payroll
            $payroll = Payroll::create([
                'code'         => $this->makeCode($periodFrom, $periodTo),
                'branch_id'    => $branchId,
                'period_from'  => $periodFrom,
                'period_to'    => $periodTo,
                'total_amount' => 0,
                'status'       => 'draft',
            ]);

            $total = 0;

            foreach ($timesheets as $ts) {
                PayrollItem::create([
                    'payroll_id'          => $payroll->id,
                    'teacher_timesheet_id'=> $ts->id,
                    'teacher_id'          => $ts->teacher_id,
                    'class_session_id'    => $ts->class_session_id,
                    'amount'              => (int) $ts->amount,
                    'note'                => null,
                ]);
                $total += (int) $ts->amount;
            }

            $payroll->update(['total_amount' => $total]);
        });

        return redirect()->route('manager.payrolls.index')->with('success', 'Đã tạo kỳ lương từ timesheets.');
    }

    /**
     * Xem chi tiết payroll + items.
     */
    public function show(Payroll $payroll, Request $request)
    {
        $perPage = (int)($request->integer('per_page') ?: 20);

        $payroll->load(['branch:id,name', 'approver:id,name']);

        $items = PayrollItem::with([
                'teacher:id,name',
                'session:id,class_id,date,start_time,end_time,room_id',
                'session.classroom:id,code,name',
            ])
            ->join('teacher_timesheets', 'payroll_items.teacher_timesheet_id', '=', 'teacher_timesheets.id')
            ->select('payroll_items.*', 'teacher_timesheets.status as timesheet_status')
            ->where('payroll_id', $payroll->id)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Manager/Payrolls/Show', [
            'payroll' => $payroll,
            'items' => $items,
            'filters' => [
                'perPage' => $perPage,
                // Add any other filters you need
            ]
        ]);
    }

    /**
     * Duyệt payroll (draft -> approved).
     */
    public function approve(ApprovePayrollRequest $request, Payroll $payroll)
    {
        if (!$payroll->isDraft()) {
            return back()->with('error', 'Chỉ có thể duyệt kỳ lương ở trạng thái "nháp".');
        }

        $payroll->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        // Ghi log
        activity_log()->log(
            $request->user()?->id,
            'payroll.approved',
            $payroll,  // target: Payroll
            [
                'payroll_id' => $payroll->id,
                'branch_id'  => $payroll->branch_id,
                'period_from'=> $payroll->period_from,
                'period_to'  => $payroll->period_to,
                'total_amount'=> (int) $payroll->total_amount,
            ]
        );

        return back()->with('success', 'Đã duyệt kỳ lương.');
    }

    /**
     * Khoá payroll (approved -> locked).
     */
    public function lock(LockPayrollRequest $request, Payroll $payroll)
    {
        if (!$payroll->isApproved()) {
            return back()->with('error', 'Chỉ có thể khoá kỳ lương đã được duyệt.');
        }

        $payroll->update(['status' => 'locked']);

        return back()->with('success', 'Đã khoá kỳ lương.');
    }

    /**
     * Xoá payroll (chỉ khi draft).
     */
    public function destroy(Payroll $payroll)
    {
        if (!$payroll->isDraft()) {
            return back()->with('error', 'Chỉ có thể xoá kỳ lương còn trạng thái "nháp".');
        }

        DB::transaction(function () use ($payroll) {
            PayrollItem::where('payroll_id', $payroll->id)->delete();
            $payroll->delete();
        });

        return redirect()->route('manager.payrolls.index')->with('success', 'Đã xoá kỳ lương.');
    }

    /**
     * Sinh mã kỳ lương: PR-yyyymmdd-yyyymmdd-XXXX
     */
    private function makeCode(string $from, string $to): string
    {
        $f = str_replace('-', '', $from);
        $t = str_replace('-', '', $to);
        return 'PR-' . $f . '-' . $t . '-' . Str::upper(Str::random(4));
    }
}

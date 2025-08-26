<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveTimesheetRequest;
use App\Http\Requests\BulkApproveTimesheetRequest;
use App\Models\TeacherTimesheet;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TimesheetController extends Controller
{
    public function index(Request $request)
    {
        // Quyền xem: kiểm tra vai trò thẳng tại đây (chưa dùng Policy)
        abort_unless($request->user()?->hasAnyRole(['admin','manager']), 403);

        $filters = [
            'status'  => $request->input('status', 'draft'),
            'branch'  => $request->input('branch', 'all'),
            'perPage' => (int) $request->input('per_page', 20),
        ];

        $q = TeacherTimesheet::query()
            ->with([
                'teacher:id,name',
                'session:id,class_id,date,start_time,end_time,room_id',
                'session.classroom:id,code,name,branch_id',
                'session.room:id,code,name',
            ])
            ->when($filters['status'] !== 'all', fn($q) => $q->where('status', $filters['status']))
            ->orderByDesc('id');

        if ($filters['branch'] !== 'all') {
            $branchId = (int) $filters['branch'];
            $q->whereHas('session.classroom', fn($qq) => $qq->where('branch_id', $branchId));
        }

        $branches   = Branch::select('id','name')->orderBy('name')->get();
        $timesheets = $q->paginate($filters['perPage'])->withQueryString();

        return Inertia::render('Manager/Timesheets/Index', [
            'timesheets' => $timesheets,
            'branches'   => $branches,
            'filters'    => $filters,
        ]);
    }

    public function approve(ApproveTimesheetRequest $request, int $id)
    {
        $ts = TeacherTimesheet::with(['session.classroom'])->findOrFail($id);

        if ($ts->status !== 'draft') {
            return back()->with('error', 'Timesheet không ở trạng thái nháp.');
        }

        $ts->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Đã duyệt timesheet.');
    }

    public function bulkApprove(BulkApproveTimesheetRequest $request)
    {
        $ids    = collect($request->validated()['ids'])->unique()->values();
        $userId = $request->user()->id;

        $updated = 0;
        DB::transaction(function () use ($ids, $userId, &$updated) {
            $updated = TeacherTimesheet::whereIn('id', $ids)
                ->where('status', 'draft')
                ->update([
                    'status'      => 'approved',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'updated_at'  => now(),
                ]);
        });

        if ($updated === 0) {
            return back()->with('error', 'Không có bản ghi nào ở trạng thái nháp để duyệt.');
        }

        return back()->with('success', "Đã duyệt {$updated} timesheet.");
    }
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Khoảng ngày xem lịch (mặc định 14 ngày tới)
        $from = $request->query('from') ?: now()->toDateString();
        $to = $request->query('to') ?: now()->addDays(14)->toDateString();

        // Sử dụng Carbon để parse và set múi giờ (sử dụng parse() để linh hoạt hơn và tránh lỗi format)
        $fromDate = Carbon::parse($from)->startOfDay()->setTimezone(config('app.timezone', 'UTC'));
        $toDate = Carbon::parse($to)->endOfDay()->setTimezone(config('app.timezone', 'UTC'));

        // Query cơ bản cho assigned sessions (thêm join với users qua teaching_assignments)
        $assignedQuery = DB::table('class_sessions as cs')
            ->join('classrooms as c', 'c.id', '=', 'cs.class_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'cs.room_id')
            ->leftJoin('branches as b', 'b.id', '=', 'c.branch_id')
            ->leftJoin('teaching_assignments as ta', function ($join) {
                $join->on('ta.class_id', '=', 'cs.class_id')
                     ->whereNull('ta.effective_to'); // Chỉ lấy assignment hiện tại
            })
            ->leftJoin('users as u', 'u.id', '=', 'ta.teacher_id') // Join với users để lấy tên giáo viên
            ->whereBetween('cs.date', [$fromDate, $toDate])
            ->whereExists(function ($q) use ($user) {
                $q->from('teaching_assignments as ta')
                    ->whereColumn('ta.class_id', 'cs.class_id')
                    ->where('ta.teacher_id', $user->id)
                    ->where(function ($w) {
                        $w->whereNull('ta.effective_from')
                          ->orWhere('ta.effective_from', '<=', DB::raw('cs.date'));
                    })
                    ->where(function ($w) {
                        $w->whereNull('ta.effective_to')
                          ->orWhere('ta.effective_to', '>=', DB::raw('cs.date'));
                    });
            });

        // Query cơ bản cho substitutions (thêm join với teaching_assignments và users để lấy tên giáo viên gốc)
        $subsQuery = DB::table('session_substitutions as ss')
            ->join('class_sessions as cs', 'cs.id', '=', 'ss.class_session_id')
            ->join('classrooms as c', 'c.id', '=', 'cs.class_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'cs.room_id')
            ->leftJoin('branches as b', 'b.id', '=', 'c.branch_id')
            ->leftJoin('teaching_assignments as ta', function ($join) {
                $join->on('ta.class_id', '=', 'cs.class_id')
                     ->whereNull('ta.effective_to'); // Chỉ lấy assignment hiện tại
            })
            ->leftJoin('users as u', 'u.id', '=', 'ta.teacher_id') // Join với users để lấy tên giáo viên gốc (được phân công)
            ->where('ss.substitute_teacher_id', $user->id)
            ->whereBetween('cs.date', [$fromDate, $toDate]);

        // Áp dụng filter branch nếu có
        if ($branchId = $request->query('branch_id')) {
            $assignedQuery->where('c.branch_id', $branchId);
            $subsQuery->where('c.branch_id', $branchId);
        }

        // Áp dụng filter class nếu có
        if ($classId = $request->query('class_id')) {
            $assignedQuery->where('c.id', $classId);
            $subsQuery->where('c.id', $classId);
        }

        // Select cho assigned (thêm teacher_name)
        $assigned = $assignedQuery->selectRaw("
            cs.id,
            cs.date,
            cs.start_time,
            cs.end_time,
            cs.status,
            c.id as class_id,
            c.code as class_code,
            c.name as class_name,
            r.code as room_code,
            r.name as room_name,
            b.name as branch_name,
            u.name as teacher_name,  -- Tên giáo viên từ teaching_assignments
            false as is_substitution
        ");

        // Select cho subs (thêm teacher_name gốc)
        $subs = $subsQuery->selectRaw("
            cs.id,
            cs.date,
            cs.start_time,
            cs.end_time,
            cs.status,
            c.id as class_id,
            c.code as class_code,
            c.name as class_name,
            r.code as room_code,
            r.name as room_name,
            b.name as branch_name,
            u.name as teacher_name,  -- Tên giáo viên gốc (được phân công)
            true as is_substitution
        ");

        // Hợp nhất & sắp xếp
        $union = $assigned->unionAll($subs);
        $rows = DB::query()
            ->fromSub($union, 't')
            ->orderBy('t.date')
            ->orderBy('t.start_time')
            ->get();

        // Query danh sách branches mà teacher có thể truy cập
        $branches = DB::table('branches as b')
            ->join('classrooms as c', 'c.branch_id', '=', 'b.id')
            ->whereExists(function ($q) use ($user) {
                $q->from('teaching_assignments as ta')
                    ->whereColumn('ta.class_id', 'c.id')
                    ->where('ta.teacher_id', $user->id);
            })
            ->orWhereExists(function ($q) use ($user) {
                $q->from('session_substitutions as ss')
                    ->join('class_sessions as cs', 'cs.id', '=', 'ss.class_session_id')
                    ->whereColumn('cs.class_id', 'c.id')
                    ->where('ss.substitute_teacher_id', $user->id);
            })
            ->select('b.id', 'b.name')
            ->distinct()
            ->get();

        // Query danh sách classes mà teacher có thể truy cập
        $classes = DB::table('classrooms as c')
            ->whereExists(function ($q) use ($user) {
                $q->from('teaching_assignments as ta')
                    ->whereColumn('ta.class_id', 'c.id')
                    ->where('ta.teacher_id', $user->id);
            })
            ->orWhereExists(function ($q) use ($user) {
                $q->from('session_substitutions as ss')
                    ->join('class_sessions as cs', 'cs.id', '=', 'ss.class_session_id')
                    ->whereColumn('cs.class_id', 'c.id')
                    ->where('ss.substitute_teacher_id', $user->id);
            })
            ->select('c.id', 'c.code', 'c.name')
            ->distinct()
            ->get();

        return inertia('Teacher/Schedule/Index', [
            'items' => $rows,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'branch_id' => $request->query('branch_id'),
                'class_id' => $request->query('class_id'),
            ],
            'branches' => $branches,
            'classes' => $classes,
        ]);
    }
}

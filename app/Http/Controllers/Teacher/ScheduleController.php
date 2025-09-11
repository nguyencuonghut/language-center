<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $user  = $request->user();

        // Khoảng ngày xem lịch (mặc định 14 ngày tới)
        $from = $request->query('from') ?: now()->toDateString();
        $to   = $request->query('to')   ?: now()->addDays(14)->toDateString();

        // --- Lịch theo PHÂN CÔNG (teaching_assignments) ---
        $assigned = DB::table('class_sessions as cs')
            ->join('classrooms as c', 'c.id', '=', 'cs.class_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'cs.room_id')
            ->leftJoin('branches as b', 'b.id', '=', 'c.branch_id')
            ->whereBetween('cs.date', [$from, $to])
            ->whereExists(function ($q) use ($user) {
                $q->from('teaching_assignments as ta')
                    ->whereColumn('ta.class_id', 'cs.class_id')
                    ->where('ta.teacher_id', $user->id)
                    // hiệu lực theo ngày buổi học
                    ->where(function ($w) {
                        $w->whereNull('ta.effective_from')
                          ->orWhere('ta.effective_from', '<=', DB::raw('cs.date'));
                    })
                    ->where(function ($w) {
                        $w->whereNull('ta.effective_to')
                          ->orWhere('ta.effective_to', '>=', DB::raw('cs.date'));
                    });
            })
            ->selectRaw("
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
                false as is_substitution
            ");

        // --- Lịch DẠY THAY (session_substitutions) ---
        // Bảng này bạn đã tạo ở bước dạy thay; nếu tên khác, sửa lại giúp nhé.
        $subs = DB::table('session_substitutions as ss')
            ->join('class_sessions as cs', 'cs.id', '=', 'ss.class_session_id')
            ->join('classrooms as c', 'c.id', '=', 'cs.class_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'cs.room_id')
            ->leftJoin('branches as b', 'b.id', '=', 'c.branch_id')
            ->where('ss.substitute_teacher_id', $user->id)
            ->whereBetween('cs.date', [$from, $to])
            ->selectRaw("
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
                true as is_substitution
            ");

        // Hợp nhất & sắp xếp
        $union = $assigned->unionAll($subs);

        $rows = DB::query()
            ->fromSub($union, 't')
            ->orderBy('t.date')
            ->orderBy('t.start_time')
            ->get();

        return inertia('Teacher/Schedule', [
            'items' => $rows,
            'filters' => [
                'from' => $from,
                'to'   => $to,
            ],
        ]);
    }
}

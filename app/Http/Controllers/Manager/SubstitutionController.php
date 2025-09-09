<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubstitutionController extends Controller
{
    public function index(Request $request)
    {
        // filters: branch, class_id, teacher_id, date_from, date_to, q (mã/ tên lớp), per_page
        $perPage   = (int)($request->integer('per_page') ?: 20);
        $branchId  = $request->input('branch');
        $classId   = $request->input('class_id');
        $teacherId = $request->input('teacher_id');
        $from      = $request->input('date_from');
        $to        = $request->input('date_to');
        $q         = trim((string)$request->input('q', ''));

        $base = DB::table('session_substitutions as ss')
            ->join('class_sessions as cs', 'cs.id', '=', 'ss.class_session_id')
            ->join('classrooms as c', 'c.id', '=', 'cs.class_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'cs.room_id')
            ->join('users as t', 't.id', '=', 'ss.substitute_teacher_id')
            ->leftJoin('branches as b', 'b.id', '=', 'c.branch_id')
            ->select([
                'ss.id',
                'ss.class_session_id',
                'ss.substitute_teacher_id',
                'ss.rate_override',
                'ss.reason',
                'ss.approved_by',
                'ss.approved_at',
                'ss.created_at',

                'cs.date',
                'cs.start_time',
                'cs.end_time',
                'cs.status as session_status',

                'c.id as class_id',
                'c.code as class_code',
                'c.name as class_name',
                'c.branch_id',

                't.name as teacher_name',
                't.id as teacher_id',

                'r.code as room_code',
                'r.name as room_name',

                'b.name as branch_name',
            ])
            ->orderByDesc('ss.created_at');

        if ($branchId && $branchId !== 'all') {
            $base->where('c.branch_id', (int)$branchId);
        }
        if ($classId) {
            $base->where('c.id', (int)$classId);
        }
        if ($teacherId) {
            $base->where('ss.substitute_teacher_id', (int)$teacherId);
        }
        if ($from) {
            $base->where('cs.date', '>=', $from);
        }
        if ($to) {
            $base->where('cs.date', '<=', $to);
        }
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('c.code', 'like', "%{$q}%")
                  ->orWhere('c.name', 'like', "%{$q}%");
            });
        }

        $subs = $base->paginate($perPage)->appends($request->query());

        // dropdown data
        $branches = DB::table('branches')->select('id','name')->orderBy('name')->get();
        $classrooms  = DB::table('classrooms')->select('id','code','name')->orderBy('code')->get()
            ->map(fn($c)=>['id'=>$c->id,'label'=>"$c->code · $c->name",'value'=>$c->id])->all();
        $teachers = DB::table('users')
            ->join('model_has_roles','model_has_roles.model_id','=','users.id')
            ->join('roles','roles.id','=','model_has_roles.role_id')
            ->where('roles.name','teacher')
            ->select('users.id','users.name')
            ->orderBy('users.name')->get()
            ->map(fn($u)=>['id'=>$u->id,'label'=>$u->name,'value'=>$u->id])->all();

        return inertia('Manager/Substitutions/Index', [
            'substitutions' => $subs,
            'filters' => [
                'branch'    => $branchId ?: 'all',
                'class_id'  => $classId,
                'teacher_id'=> $teacherId,
                'date_from' => $from,
                'date_to'   => $to,
                'q'         => $q,
                'perPage'   => $perPage,
            ],
            'branches' => $branches,
            'classrooms'  => $classrooms,
            'teachers' => $teachers,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\TeachingAssignment;
use App\Models\ClassSession;
use App\Models\Attendance;
use App\Models\Enrollment;
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

    public function week(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();

        // Lấy danh sách chi nhánh mà Teacher dạy (dựa trên teaching_assignments và session_substitutions)
        $teacherBranches = DB::table('branches as b')
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

        // Kiểm tra số lượng chi nhánh
        $branchCount = $teacherBranches->count();
        $defaultBranchId = null; // Mặc định 'Tất cả'
        if ($branchCount === 1) {
            $defaultBranchId = $teacherBranches->first()->id; // Chỉ hiển thị tên, không cho chọn
        }

        // Lấy danh sách lớp mà Teacher dạy (dựa trên teaching_assignments và session_substitutions)
        $teacherClasses = DB::table('classrooms as c')
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

        // Kiểm tra số lượng lớp
        $classCount = $teacherClasses->count();
        $defaultClassId = null; // Mặc định 'Tất cả'
        if ($classCount === 1) {
            $defaultClassId = $teacherClasses->first()->id;
        }

        // Lấy filter
        $branchId = $request->filled('branch_id') ? $request->branch_id : $defaultBranchId;
        $classId = $request->filled('class_id') ? $request->class_id : $defaultClassId;
        $weekStart = $request->filled('week_start') ? \Carbon\Carbon::parse($request->week_start) : \Carbon\Carbon::now()->startOfWeek();

        // Đảm bảo branch filter nằm trong quyền của teacher
        if ($branchId && !$teacherBranches->pluck('id')->contains($branchId)) {
            abort(403, 'Bạn không có quyền xem chi nhánh này');
        }

        // Đảm bảo class filter nằm trong quyền của teacher
        if ($classId && !$teacherClasses->pluck('id')->contains($classId)) {
            abort(403, 'Bạn không có quyền xem lớp này');
        }

        // Tính ngày đầu và cuối tuần
        $start = $weekStart->copy()->startOfWeek();
        $end = $weekStart->copy()->endOfWeek();

        // Query sessions: tất cả ClassSession mà Teacher dạy chính hoặc dạy thay trong tuần
        $sessionQuery = DB::table('class_sessions as cs')
            ->join('classrooms as c', 'c.id', '=', 'cs.class_id')
            ->leftJoin('rooms as r', 'r.id', '=', 'cs.room_id')
            ->leftJoin('branches as b', 'b.id', '=', 'c.branch_id')
            ->leftJoin('teaching_assignments as ta', function ($join) {
                $join->on('ta.class_id', '=', 'cs.class_id')
                    ->whereNull('ta.effective_to'); // Chỉ lấy assignment hiện tại
            })
            ->leftJoin('users as u', 'u.id', '=', 'ta.teacher_id') // Join với users để lấy tên giáo viên gốc
            ->whereBetween('cs.date', [$start->toDateString(), $end->toDateString()])
            ->where(function ($q) use ($user) {
                // Dạy chính: dựa trên teaching_assignments
                $q->whereExists(function ($subQ) use ($user) {
                    $subQ->from('teaching_assignments as ta')
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
                })
                // Hoặc dạy thay: dựa trên session_substitutions
                ->orWhereExists(function ($subQ) use ($user) {
                    $subQ->from('session_substitutions as ss')
                        ->whereColumn('ss.class_session_id', 'cs.id')
                        ->where('ss.substitute_teacher_id', $user->id);
                });
            });

        // Áp dụng filter branch nếu có
        if ($branchId) {
            $sessionQuery->where('c.branch_id', $branchId);
        }

        // Áp dụng filter class nếu có
        if ($classId) {
            $sessionQuery->where('c.id', $classId);
        }

        // Select và map sessions
        $sessions = $sessionQuery->selectRaw("
            cs.id,
            cs.date,
            cs.start_time,
            cs.end_time,
            cs.status,
            cs.note,
            c.id as class_id,
            c.code as class_code,
            c.name as class_name,
            r.code as room_code,
            r.name as room_name,
            b.name as branch_name,
            u.name as teacher_name,  -- Tên giáo viên gốc
            CASE WHEN ss.id IS NOT NULL THEN true ELSE false END as is_substitution
        ")
        ->leftJoin('session_substitutions as ss', function ($join) use ($user) {
            $join->on('ss.class_session_id', '=', 'cs.id')
                ->where('ss.substitute_teacher_id', $user->id);
        })
        ->orderBy('cs.date')
        ->orderBy('cs.start_time')
        ->get();

        $sessions = $sessions->map(function ($s) use ($user) {
            return [
                'id' => $s->id,
                'date' => $s->date instanceof \Carbon\Carbon ? $s->date->toDateString() : (string)$s->date,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time' => substr($s->end_time, 0, 5),
                'status' => $s->status,
                'note' => $s->note,
                'room' => $s->room_code ? ['id' => null, 'code' => $s->room_code, 'name' => $s->room_name] : null, // Giả sử không có id room, adjust nếu cần
                'classroom' => ['id' => $s->class_id, 'code' => $s->class_code, 'name' => $s->class_name],
                'substitution' => $s->is_substitution ? ['id' => $user->id, 'name' => $user->name] : null, // Giả sử substitute là user hiện tại
                'is_conflict' => false,
            ];
        });

        // Chuẩn bị dữ liệu tuần
        $days = [];
        $cur = $start->copy();
        while ($cur->lte($end)) {
            $iso = $cur->toDateString();
            $label = $cur->translatedFormat('D d/m');
            $items = $sessions->where('date', $iso)->values();
            $days[] = [
                'iso' => $iso,
                'label' => $label,
                'items' => $items,
            ];
            $cur->addDay();
        }

        return inertia('Teacher/Schedule/Week', [
            'filters' => [
                'branch_id' => $branchId,
                'class_id' => $classId,
                'week_start' => $start->toDateString(),
            ],
            'week' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days' => $days,
            ],
            'branches' => $teacherBranches->map(fn($b) => ['id' => $b->id, 'name' => $b->name]),
            'classes' => $teacherClasses->map(fn($c) => ['id' => $c->id, 'code' => $c->code, 'name' => $c->name]),
        ]);
    }

    public function sessionMeta(Request $request, ClassSession $session)
    {
        // Load relations cơ bản
        $session->load([
            'classroom:id,code,name,branch_id',
            'room:id,code,name',
            'classroom.branch:id,name',
            'substitution',
        ]);

        // Lấy GV hiệu lực tại ngày buổi học (nếu bạn có effective_from/to)
        $teachers = TeachingAssignment::with('teacher:id,name')
            ->where('class_id', $session->class_id)
            ->when(true, function ($q) use ($session) {
                // hiệu lực tại ngày buổi học
                $q->where(function ($qq) use ($session) {
                    $qq->whereNull('effective_from')->orWhere('effective_from', '<=', $session->date);
                })->where(function ($qq) use ($session) {
                    $qq->whereNull('effective_to')->orWhere('effective_to', '>=', $session->date);
                });
            })
            ->get()
            ->map(fn($ta) => ['id' => $ta->teacher_id, 'name' => $ta->teacher->name])
            ->values();

        // Lấy danh sách các giao viên có thể dạy thay lớp này
        $currentTeacherIds = $teachers->pluck('id')->all();
        $substitutes = User::whereHas('roles', fn($q) => $q->where('name', 'teacher'))
            ->whereNotIn('id', $currentTeacherIds)
            ->get(['id', 'name']);

        // Điểm danh đã nhập (map theo student_id)
        $attMap = Attendance::where('class_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

        // Học viên đã ghi danh và đến buổi này (status active, vào không muộn hơn session_no)
        $enrollments = Enrollment::with('student:id,code,name')
            ->where('class_id', $session->class_id)
            ->where('status', 'active')
            ->where('start_session_no', '<=', $session->session_no)
            ->orderByDesc('id')
            ->get()
            ->map(function ($en) use ($attMap) {
                $att = $attMap->get($en->student_id);
                return [
                    'id'        => $en->student_id,
                    'code'      => $en->student->code,
                    'name'      => $en->student->name,
                    'status'    => $att ? $att->status : null,   // present|absent|late|excused|null
                    'note'      => $att ? $att->note : null,
                ];
            })
            ->values();

        // (Tuỳ chọn) kiểm tra xung đột phòng — đơn giản, cùng ngày, cùng phòng, trùng giờ
        $roomConflicts = [];
        if ($session->room_id) {
            $roomConflicts = ClassSession::with(['classroom:id,code,name'])
                ->where('id', '!=', $session->id)
                ->where('room_id', $session->room_id)
                ->where('date', $session->date)
                ->where(function ($q) use ($session) {
                    $q->whereBetween('start_time', [$session->start_time, $session->end_time])
                    ->orWhereBetween('end_time',   [$session->start_time, $session->end_time])
                    ->orWhere(function ($qq) use ($session) {
                        $qq->where('start_time', '<=', $session->start_time)
                            ->where('end_time',   '>=', $session->end_time);
                    });
                })
                ->orderBy('start_time')
                ->get()
                ->map(fn($s) => [
                    'id' => $s->id,
                    'class' => $s->classroom?->code . ' · ' . $s->classroom?->name,
                    'time'  => substr($s->start_time,0,5) . '–' . substr($s->end_time,0,5),
                ])
                ->values();
        }

        // (Tuỳ chọn) xung đột GV (nếu cần, làm tương tự dựa trên TeachingAssignment)

        return response()->json([
            'session' => [
                'id'         => $session->id,
                'date'       => $session->date,
                'start_time' => substr($session->start_time,0,5),
                'end_time'   => substr($session->end_time,0,5),
                'status'     => $session->status,
                'note'       => $session->note,
                'classroom'  => [
                    'id'   => $session->classroom->id,
                    'code' => $session->classroom->code,
                    'name' => $session->classroom->name,
                    'branch'=> $session->classroom->branch?->name,
                ],
                'room' => $session->room ? [
                    'id' => $session->room->id,
                    'code' => $session->room->code,
                    'name' => $session->room->name,
                ] : null,
                'substitution' => $session->substitution ? [ // Thêm substitution vào response
                    'id' => $session->substitution->substitute_teacher_id,
                    'name' => $session->substitution->substituteTeacher?->name,
                ] : null,
            ],
            'teachers'    => $teachers,       // danh sách GV hiệu lực
            'substitutes' => $substitutes,    // danh sách GV dạy thay (nếu có)
            'enrollments' => $enrollments,    // <<< CÁI BẠN ĐANG CẦN
            'conflicts'   => [
                'room'    => $roomConflicts,
                'teacher' => [], // TODO: bổ sung nếu bạn cần
            ],
        ]);
    }
}

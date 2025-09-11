<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        // ---- Validate nhẹ filters ----
        $validated = $request->validate([
            'branch_id'  => ['nullable','integer','exists:branches,id'],
            'class_id'   => ['nullable','integer','exists:classes,id'],
            'teacher_id' => ['nullable','integer','exists:users,id'],
            'from'       => ['nullable','date'],
            'to'         => ['nullable','date','after_or_equal:from'],
            'order'      => ['nullable','in:asc,desc'],
            'per_page'   => ['nullable','integer','in:20,50,100'],
        ]);

        // ---- Khoảng thời gian mặc định: tuần hiện tại ----
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->toDateString()
            : now()->startOfWeek()->toDateString();
        $to   = isset($validated['to'])
            ? Carbon::parse($validated['to'])->toDateString()
            : now()->endOfWeek()->toDateString();

        $branchId  = $validated['branch_id']  ?? null;
        $classId   = $validated['class_id']   ?? null;
        $teacherId = $validated['teacher_id'] ?? null;
        $order     = $validated['order']      ?? 'asc';
        $perPage   = (int)($validated['per_page'] ?? 20);

        // ---- Query sessions (List view) ----
        $q = ClassSession::query()
            ->with([
                'room:id,code,name',
                'classroom:id,code,name,branch_id',
            ])
            ->whereBetween('date', [$from, $to])
            ->when($branchId, fn($qq) =>
                $qq->whereHas('classroom', fn($c) => $c->where('branch_id', $branchId))
            )
            ->when($classId, fn($qq) => $qq->where('class_id', $classId));

        // Lọc theo giáo viên: theo phân công (teaching_assignments) hoặc dạy thay (session_substitutions) nếu bạn đã có bảng này
        if ($teacherId) {
            $q->where(function($w) use ($teacherId) {
                // dạy thay
                $w->whereHas('substitution', fn($s) => $s->where('substitute_teacher_id', $teacherId))
                  // giáo viên theo phân công hiệu lực tại ngày buổi
                  ->orWhereHas('classroom.teachingAssignments', function($ta){
                      // join condition xử lý ở callback bên dưới
                  });
            });

            // hack nhỏ cho whereHas with date range hiệu lực (nếu bạn có cột effective_from/to)
            $q->whereHas('classroom.teachingAssignments', function($ta) use ($teacherId) {
                $ta->where('teacher_id', $teacherId);
                // Nếu bạn dùng hiệu lực theo ngày buổi:
                // $ta->where(function($d){
                //     $d->whereNull('effective_from')->orWhere('effective_from','<=',now()->toDateString());
                // })->where(function($d){
                //     $d->whereNull('effective_to')->orWhere('effective_to','>=',now()->toDateString());
                // });
            });
        }

        $q->orderBy('date', $order)->orderBy('start_time', $order);

        $paginator = $q->paginate($perPage)->appends($request->query());

        // ---- Map data trả ra FE (gọn) ----
        $sessions = $paginator->through(function (ClassSession $s) {
            // Nếu có quan hệ substitution() cho dạy thay
            $sub = method_exists($s, 'substitution') ? $s->substitution : null;

            return [
                'id'          => $s->id,
                'date'        => $s->date,
                'start_time'  => substr($s->start_time, 0, 5),
                'end_time'    => substr($s->end_time, 0, 5),
                'status'      => $s->status, // planned|moved|canceled
                'note'        => $s->note,
                'room'        => $s->room ? ['id'=>$s->room->id,'code'=>$s->room->code,'name'=>$s->room->name] : null,
                'classroom'   => $s->classroom ? ['id'=>$s->classroom->id,'code'=>$s->classroom->code,'name'=>$s->classroom->name] : null,
                // bạn có thể bổ sung teachers hiệu lực nếu cần (join phức tạp) — tạm thời hiển thị dạy thay nếu có
                'substitute'  => $sub ? ['id'=>$sub->substitute_teacher_id, 'name'=>$sub->substituteTeacher?->name] : null,
                'is_conflict' => false, // TODO: cắm Room/TeacherConflictService nếu đã có
            ];
        });

        // ---- Dropdowns ----
        $branches = Branch::query()->select('id','name')->orderBy('name')->get();
        $classes  = Classroom::query()
            ->when($branchId, fn($qq) => $qq->where('branch_id', $branchId))
            ->select('id','code','name')->orderBy('code')->get()
            ->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]);
        $teachers = User::query()
            ->whereHas('roles', fn($r) => $r->where('name','teacher'))
            ->select('id','name')->orderBy('name')->get();

        return Inertia::render('Manager/Schedule/Index', [
            'filters' => [
                'branch_id'  => $branchId,
                'class_id'   => $classId,
                'teacher_id' => $teacherId,
                'from'       => $from,
                'to'         => $to,
                'order'      => $order,
                'perPage'    => $perPage,
            ],
            'branches' => $branches,
            'classes'  => $classes,
            'teachers' => $teachers,
            'sessions' => $sessions, // paginator
        ]);
    }

    public function week(\Illuminate\Http\Request $request)
    {
        $user = auth()->user();
        // Lấy các branch mà manager được phân quyền
        $branchIds = $user->managerBranches()->pluck('branches.id')->all();

        // Lấy filter
        $branchId   = $request->filled('branch_id')   ? $request->branch_id   : null;
        $classId    = $request->filled('class_id')    ? $request->class_id    : null;
        $teacherId  = $request->filled('teacher_id')  ? $request->teacher_id  : null;
        $weekStart  = $request->filled('week_start')  ? \Carbon\Carbon::parse($request->week_start) : \Carbon\Carbon::now()->startOfWeek();

        // Đảm bảo branch filter nằm trong quyền của manager
        if ($branchId && !in_array($branchId, $branchIds)) {
            abort(403, 'Bạn không có quyền xem chi nhánh này');
        }

        // Tính ngày đầu và cuối tuần
        $start = $weekStart->copy()->startOfWeek();
        $end   = $weekStart->copy()->endOfWeek();

        // Lấy danh sách lớp thuộc các branch manager quản lý
        $classQuery = \App\Models\Classroom::query()
            ->whereIn('branch_id', $branchIds)
            ->where('status', 'open');

        if ($branchId) {
            $classQuery->where('branch_id', $branchId);
        }
        if ($classId) {
            $classQuery->where('id', $classId);
        }
        $classes = $classQuery->get();

        // Lấy danh sách giáo viên thuộc các lớp này
        $teacherIds = \App\Models\TeachingAssignment::whereIn('class_id', $classes->pluck('id'))
            ->pluck('teacher_id')->unique()->values();
        $teachers = \App\Models\User::whereIn('id', $teacherIds)->get();

        // Lấy các session trong tuần theo filter
        $sessionQuery = \App\Models\ClassSession::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('class_id', $classes->pluck('id'));

        if ($teacherId) {
            $sessionQuery->whereHas('teachingAssignments', function($q) use ($teacherId) {
                $q->where('teacher_id', $teacherId);
            });
        }

        $sessions = $sessionQuery
            ->with(['classroom', 'room', 'substitution'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();
        $sessions = $sessions->map(function($s) {
            return [
                'id'         => $s->id,
                'date'       => $s->date instanceof \Carbon\Carbon ? $s->date->toDateString() : (string)$s->date,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time'   => substr($s->end_time, 0, 5),
                'status'     => $s->status,
                'note'       => $s->note,
                'room'       => $s->room ? ['id'=>$s->room->id,'code'=>$s->room->code,'name'=>$s->room->name] : null,
                'classroom'  => $s->classroom ? ['id'=>$s->classroom->id,'code'=>$s->classroom->code,'name'=>$s->classroom->name] : null,
                'substitute' => method_exists($s, 'substitution') && $s->substitution
                    ? ['id'=>$s->substitution->substitute_teacher_id, 'name'=>$s->substitution->substituteTeacher?->name]
                    : null,
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

        return inertia('Manager/Schedule/Week', [
            'filters'  => [
                'branch_id'   => $branchId,
                'class_id'    => $classId,
                'teacher_id'  => $teacherId,
                'week_start'  => $start->toDateString(),
            ],
            'week' => [
                'start' => $start->toDateString(),
                'end'   => $end->toDateString(),
                'days'  => $days,
            ],
            'branches' => \App\Models\Branch::whereIn('id', $branchIds)->where('active', 1)->get(['id','name']),
            'classes'  => $classes->map(fn($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
            ]),
            'teachers' => $teachers->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
            ]),
        ]);
    }
}

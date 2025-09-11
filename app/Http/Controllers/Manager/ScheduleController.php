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

    public function week(Request $request)
    {
        $validated = $request->validate([
            'branch_id'  => ['nullable','integer','exists:branches,id'],
            'class_id'   => ['nullable','integer','exists:classes,id'],
            'teacher_id' => ['nullable','integer','exists:users,id'],
            'week_start' => ['nullable','date'], // ngày bất kỳ trong tuần hoặc đúng thứ 2/Chủ nhật — ta sẽ normalize
        ]);

        // Chuẩn hoá đầu tuần (Mon)
        $ws = isset($validated['week_start'])
            ? CarbonImmutable::parse($validated['week_start'])
            : CarbonImmutable::now();
        // Bắt đầu từ thứ Hai (ISO week)
        $weekStart = $ws->startOfWeek(CarbonImmutable::MONDAY);
        $weekEnd   = $weekStart->endOfWeek(CarbonImmutable::SUNDAY);

        $branchId  = $validated['branch_id']  ?? null;
        $classId   = $validated['class_id']   ?? null;
        $teacherId = $validated['teacher_id'] ?? null;

        $q = ClassSession::query()
            ->with([
                'room:id,code,name',
                'classroom:id,code,name,branch_id',
            ])
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when($branchId, fn($qq) =>
                $qq->whereHas('classroom', fn($c) => $c->where('branch_id', $branchId))
            )
            ->when($classId, fn($qq) => $qq->where('class_id', $classId));

        // Lọc theo giáo viên (phân công + dạy thay nếu bạn có bảng dạy thay)
        if ($teacherId) {
            $q->where(function($w) use ($teacherId) {
                $w->whereHas('classroom.teachingAssignments', fn($ta) => $ta->where('teacher_id', $teacherId));
                if (method_exists(\App\Models\ClassSession::class, 'substitution')) {
                    $w->orWhereHas('substitution', fn($s) => $s->where('substitute_teacher_id', $teacherId));
                }
            });
        }

        $sessions = $q->orderBy('date')->orderBy('start_time')->get()->map(function ($s) {
            return [
                'id'         => $s->id,
                'date'       => $s->date,
                'start_time' => substr($s->start_time, 0, 5),
                'end_time'   => substr($s->end_time, 0, 5),
                'status'     => $s->status,
                'note'       => $s->note,
                'room'       => $s->room ? ['id'=>$s->room->id,'code'=>$s->room->code,'name'=>$s->room->name] : null,
                'classroom'  => $s->classroom ? ['id'=>$s->classroom->id,'code'=>$s->classroom->code,'name'=>$s->classroom->name] : null,
            ];
        });

        // Build mảng 7 ngày
        $days = [];
        for ($i=0; $i<7; $i++) {
            $d = $weekStart->addDays($i);
            $ds = $d->toDateString();
            $days[] = [
                'iso'  => $ds,
                'dow'  => $d->isoWeekday(),                 // 1..7
                'dd'   => $d->format('d'),
                'mm'   => $d->format('m'),
                'yyyy' => $d->format('Y'),
                'label'=> $d->isoFormat('dd DD/MM'),        // Mo 02/09
                'items'=> $sessions->where('date', $ds)->values(),
            ];
        }

        // Dropdowns
        $branches = Branch::select('id','name')->orderBy('name')->get();
        $classes  = Classroom::when($branchId, fn($qq) => $qq->where('branch_id', $branchId))
            ->select('id','code','name')->orderBy('code')->get()
            ->map(fn($c)=>['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]);
        $teachers = User::whereHas('roles', fn($r)=>$r->where('name','teacher'))
            ->select('id','name')->orderBy('name')->get();

        return Inertia::render('Manager/Schedule/Week', [
            'filters'   => [
                'branch_id'  => $branchId,
                'class_id'   => $classId,
                'teacher_id' => $teacherId,
                'week_start' => $weekStart->toDateString(),
            ],
            'week'      => [
                'start' => $weekStart->toDateString(),
                'end'   => $weekEnd->toDateString(),
                'days'  => $days,
            ],
            'branches'  => $branches,
            'classes'   => $classes,
            'teachers'  => $teachers,
        ]);
    }
}

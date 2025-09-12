<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\ClassSession;
use App\Models\User;
use App\Models\TeachingAssignment;
use App\Models\Attendance;
use App\Models\Enrollment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        \Illuminate\Support\Facades\Log::info('ScheduleController@index', ['request' => $request->all()]);
        $user = auth()->user();
        $role = $user->roles->first()?->name; // 'admin', 'manager', 'teacher'

        // ---- Validate nhẹ filters ----
        $validated = $request->validate([
            'branch_id'  => ['nullable','integer','exists:branches,id'],
            'class_id'   => ['nullable','integer','exists:classrooms,id'],
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

        // ---- Scope queries dựa trên role ----
        $branchIds = [];
        $classIds = [];
        $sessionIds = [];
        if ($role === 'manager') {
            $branchIds = $user->managerBranches()->pluck('branches.id')->all();
            if ($branchId && !in_array($branchId, $branchIds)) {
                abort(403, 'Bạn không có quyền xem chi nhánh này');
            }
        } elseif ($role === 'teacher') {
            // Lấy classIds từ teaching assignments hiệu lực trong khoảng thời gian
            $classIds = TeachingAssignment::where('teacher_id', $user->id)
                ->where(function($q) use ($from) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
                })->where(function($q) use ($to) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $to);
                })->pluck('class_id')->unique()->values();

            // Lấy sessionIds từ substitutions trong khoảng thời gian
            $sessionIds = ClassSession::whereHas('substitution', function($q) use ($user) {
                $q->where('substitute_teacher_id', $user->id);
            })->whereBetween('date', [$from, $to])->pluck('id')->unique()->values();

            // Lấy branchIds từ classIds
            $branchIds = Classroom::whereIn('id', $classIds)->pluck('branch_id')->unique()->values();
        }

        // ---- Query sessions (List view) ----
        $q = ClassSession::query()
        ->with([
            'room:id,code,name',
            'classroom:id,code,name,branch_id',
            'classroom.teachingAssignments' => function($query) use ($from, $to) {
                $query->with('teacher:id,name')
                    ->where(function($q) use ($to) {
                        $q->whereNull('effective_from')->orWhere('effective_from', '<=', $to);
                    })
                    ->where(function($q) use ($from) {
                        $q->whereNull('effective_to')->orWhere('effective_to', '>=', $from);
                    });
            },
            'substitution.substituteTeacher:id,name',
        ])
        ->whereBetween('date', [$from, $to])
        ->when($role === 'manager' && !$branchId, fn($qq) =>
            $qq->whereHas('classroom', fn($c) => $c->whereIn('branch_id', $branchIds))
        )
        ->when($role === 'teacher', fn($qq) =>
            $qq->where(function($w) use ($sessionIds, $classIds) {
                $w->whereIn('id', $sessionIds) // Sessions dạy thay
                ->orWhereIn('class_id', $classIds); // Sessions được phân công
            })
        )
        ->when($branchId, fn($qq) =>
            $qq->whereHas('classroom', fn($c) => $c->where('branch_id', $branchId))
        )
        ->when($classId, fn($qq) => $qq->where('class_id', $classId));

        // Lọc theo giáo viên: theo phân công hoặc dạy thay
        if ($teacherId) {
            $q->where(function($w) use ($teacherId) {
                $w->whereHas('substitution', fn($s) => $s->where('substitute_teacher_id', $teacherId))
                ->orWhereHas('classroom.teachingAssignments', function($ta) use ($teacherId) {
                    $ta->where('teacher_id', $teacherId)
                        ->where(function($q) {
                            $q->whereNull('effective_from')->orWhereColumn('effective_from', '<=', now()->toDateString());
                        })
                        ->where(function($q) {
                            $q->whereNull('effective_to')->orWhereColumn('effective_to', '>=', now()->toDateString());
                        });
                });
            });
        }

        $q->orderBy('date', $order)->orderBy('start_time', $order);

        $paginator = $q->paginate($perPage)->appends($request->query());

        // ---- Map data trả ra FE ----
        $sessions = $paginator->through(function (ClassSession $s) {
            $sub = $s->substitution;

            // Tìm teacher hiệu lực tại ngày của session
            $teacher = $s->classroom->teachingAssignments
                ->where('effective_from', '<=', $s->date)
                ->where(function($ta) use ($s) {
                    return is_null($ta->effective_to) || $ta->effective_to >= $s->date;
                })
                ->first()?->teacher?->name;

            return [
                'id'          => $s->id,
                'date'        => $s->date instanceof Carbon ? $s->date->toDateString() : (string)$s->date,
                'start_time'  => substr($s->start_time, 0, 5),
                'end_time'    => substr($s->end_time, 0, 5),
                'status'      => $s->status,
                'note'        => $s->note,
                'room'        => $s->room ? ['id'=>$s->room->id,'code'=>$s->room->code,'name'=>$s->room->name] : null,
                'classroom'   => $s->classroom ? ['id'=>$s->classroom->id,'code'=>$s->classroom->code,'name'=>$s->classroom->name] : null,
                'teacher'     => $teacher, // Thêm teacher chính
                'substitute'  => $sub ? ['id'=>$sub->substitute_teacher_id, 'name'=>$sub->substituteTeacher?->name] : null,
                'is_conflict' => false,
            ];
        });

        // ---- Dropdowns (scope theo role) ----
        $branchesQuery = Branch::where('active', 1);
        if ($role === 'manager' || $role === 'teacher') {
            $branchesQuery->whereIn('id', $branchIds);
        }
        $branches = $branchesQuery->get(['id','name']);
        \Illuminate\Support\Facades\Log::info('branchIds', ['branches' => $branches]);

        // Xử lý filter Branch theo yêu cầu
        $branchOptions = [];
        $defaultBranchId = null;
        if ($role === 'admin') {
            $branchOptions = array_merge([['id' => null, 'name' => 'Tất cả']], $branches->toArray());
        } elseif ($role === 'manager' || $role === 'teacher') {
            if (count($branchIds) === 0) {
                // Teacher chưa được gán branch nào: không hiển thị filter Branch
                $branchOptions = [];
            } elseif (count($branchIds) === 1) {
                $defaultBranchId = $branchIds[0];
                $branchOptions = $branches->toArray(); // Chỉ có 1 branch
            } else {
                $branchOptions = array_merge([['id' => null, 'name' => 'Tất cả']], $branches->toArray());
            }
        }

        // Nếu không có branchId từ request và có default, set default
        if (!$branchId && $defaultBranchId) {
            $branchId = $defaultBranchId;
        }

        // Xử lý filter Class theo yêu cầu
        $classesQuery = Classroom::query()
            ->when($branchId, fn($qq) => $qq->where('branch_id', $branchId))
            ->when($role === 'manager' && !$branchId, fn($qq) => $qq->whereIn('branch_id', $branchIds))
            ->when($role === 'teacher', fn($qq) => $qq->whereIn('id', $classIds))
            ->select('id','code','name')->orderBy('code');
        $classes = $classesQuery->get()->map(fn($c) => ['id'=>$c->id,'code'=>$c->code,'name'=>$c->name]);

        // Xử lý filter Class theo yêu cầu
        $classOptions = [];
        $defaultClassId = null;
        if ($role === 'admin') {
            $classOptions = array_merge([['id' => null, 'name' => 'Tất cả']], $classes->map(fn($c) => ['id' => $c->id, 'name' => $c->code . ' · ' . $c->name])->toArray());
        } elseif ($role === 'manager' || $role === 'teacher') {
            if (count($classes) === 0) {
                // Không có class nào: không hiển thị filter Class
                $classOptions = [];
            } elseif (count($classes) === 1) {
                $defaultClassId = $classes[0]['id'];
                $classOptions = [['id' => $classes[0]['id'], 'name' => $classes[0]['code'] . ' · ' . $classes[0]['name']]];
            } else {
                $classOptions = array_merge([['id' => null, 'name' => 'Tất cả']], $classes->map(fn($c) => ['id' => $c['id'], 'name' => $c['code'] . ' · ' . $c['name']])->toArray());
            }
        }

        // Nếu không có classId từ request và có default, set default
        if (!$classId && $defaultClassId) {
            $classId = $defaultClassId;
        }

        $teachersQuery = User::query()
            ->whereHas('roles', fn($r) => $r->where('name','teacher'))
            ->select('id','name')->orderBy('name');
        if ($role === 'teacher') {
            $teachersQuery->where('id', $user->id); // chỉ GV bản thân
        }
        $teachers = $teachersQuery->get();

        return Inertia::render('Schedule/Index', [
            'filters' => [
                'branch_id'  => $branchId,
                'class_id'   => $classId,
                'teacher_id' => $teacherId,
                'from'       => $from,
                'to'         => $to,
                'order'      => $order,
                'perPage'    => $perPage,
            ],
            'branches' => $branchOptions,
            'classes'  => $classOptions,
            'teachers' => $teachers,
            'sessions' => $sessions,
        ]);
    }

    public function week(Request $request)
    {
        $user = auth()->user();
        $role = $user->roles->first()?->name;

        // Lấy filter
        $branchId   = $request->filled('branch_id')   ? $request->branch_id   : null;
        $classId    = $request->filled('class_id')    ? $request->class_id    : null;
        $teacherId  = $request->filled('teacher_id')  ? $request->teacher_id  : null;
        $weekStart  = $request->filled('week_start')  ? Carbon::parse($request->week_start) : Carbon::now()->startOfWeek();

        // Scope theo role
        $branchIds = [];
        $classIds = [];
        if ($role === 'manager') {
            $branchIds = $user->managerBranches()->pluck('branches.id')->all();
            if ($branchId && !in_array($branchId, $branchIds)) {
                abort(403, 'Bạn không có quyền xem chi nhánh này');
            }
        } elseif ($role === 'teacher') {
            $classIds = TeachingAssignment::where('teacher_id', $user->id)
                ->where(function($q) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString());
                })->where(function($q) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()->toDateString());
                })->pluck('class_id')->unique()->values();
        }

        // Tính ngày đầu và cuối tuần
        $start = $weekStart->copy()->startOfWeek();
        $end   = $weekStart->copy()->endOfWeek();

        // Lấy danh sách lớp
        $classQuery = Classroom::query()
            ->when($role === 'manager' && !$branchId, fn($qq) => $qq->whereIn('branch_id', $branchIds))
            ->when($role === 'teacher', fn($qq) => $qq->whereIn('id', $classIds))
            ->where('status', 'open');

        if ($branchId) {
            $classQuery->where('branch_id', $branchId);
        }
        if ($classId) {
            $classQuery->where('id', $classId);
        }
        $classes = $classQuery->get();

        // Lấy danh sách giáo viên
        $teacherIds = TeachingAssignment::whereIn('class_id', $classes->pluck('id'))
            ->pluck('teacher_id')->unique()->values();
        $teachersQuery = User::whereIn('id', $teacherIds);
        if ($role === 'teacher') {
            $teachersQuery->where('id', $user->id);
        }
        $teachers = $teachersQuery->get();

        // Lấy sessions
        $sessionQuery = ClassSession::query()
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
            ->get()
            ->map(function($s) {
                return [
                    'id'         => $s->id,
                    'date'       => $s->date instanceof Carbon ? $s->date->toDateString() : (string)$s->date,
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

        // Branches dropdown
        $branchesQuery = Branch::where('active', 1);
        if ($role === 'manager') {
            $branchesQuery->whereIn('id', $branchIds);
        }
        $branches = $branchesQuery->get(['id','name']);

        return Inertia::render('Schedule/Week', [
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
            'branches' => $branches,
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

    public function sessionMeta(Request $request, ClassSession $session)
    {
        $user = auth()->user();
        $role = $user->roles->first()?->name;

        // Check quyền access session
        if ($role === 'manager') {
            $branchIds = $user->managerBranches()->pluck('branches.id')->all();
            if (!in_array($session->classroom->branch_id, $branchIds)) {
                abort(403, 'Bạn không có quyền xem buổi học này');
            }
        } elseif ($role === 'teacher') {
            $hasAccess = TeachingAssignment::where('teacher_id', $user->id)
                ->where('class_id', $session->class_id)
                ->where(function($q) use ($session) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $session->date);
                })->where(function($q) use ($session) {
                    $q->whereNull('effective_from')->orWhere('effective_from', '<=', $session->date);
                })->exists();
            if (!$hasAccess) {
                abort(403, 'Bạn không có quyền xem buổi học này');
            }
        }
        // Admin: no check

        // Load relations
        $session->load([
            'classroom:id,code,name,branch_id',
            'room:id,code,name',
            'classroom.branch:id,name',
            'substitution.substituteTeacher:id,name',
        ]);

        // Teachers hiệu lực
        $teachers = TeachingAssignment::with('teacher:id,name')
            ->where('class_id', $session->class_id)
            ->where(function ($q) use ($session) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $session->date);
            })->where(function ($q) use ($session) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $session->date);
            })
            ->get()
            ->map(fn($ta) => ['id' => $ta->teacher_id, 'name' => $ta->teacher->name])
            ->values();

        // Substitutes
        $currentTeacherIds = $teachers->pluck('id')->all();
        $substitutes = User::whereHas('roles', fn($q) => $q->where('name', 'teacher'))
            ->whereNotIn('id', $currentTeacherIds)
            ->get(['id', 'name']);

        // Enrollments
        $attMap = Attendance::where('class_session_id', $session->id)
            ->get()
            ->keyBy('student_id');

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
                    'status'    => $att ? $att->status : null,
                    'note'      => $att ? $att->note : null,
                ];
            })
            ->values();

        // Conflicts
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
                'substitute' => $session->substitute ? [
                    'id' => $session->substitute->substitute_teacher_id,
                    'name' => $session->substitute->substituteTeacher?->name,
                ] : null,
            ],
            'teachers'    => $teachers,
            'substitutes' => $substitutes,
            'enrollments' => $enrollments,
            'conflicts'   => [
                'room'    => $roomConflicts,
                'teacher' => [],
            ],
        ]);
    }
}

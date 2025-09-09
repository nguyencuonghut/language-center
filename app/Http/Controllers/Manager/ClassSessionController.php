<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSessionsRequest;
use App\Http\Requests\StoreSessionRequest;
use App\Jobs\GenerateSessionsForClass;
use App\Models\Classroom;
use App\Http\Requests\UpdateSessionRequest;
use App\Models\ClassSession;
use App\Models\Room;
use App\Models\User;
use App\Services\HolidayService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassSessionController extends Controller
{
    public function __construct(private HolidayService $holidayService) {}

    public function generate(GenerateSessionsRequest $request, Classroom $classroom)
    {
        // Có thể kiểm tra nhanh lớp đã có schedule hay chưa (nhẹ) nếu muốn
        if ($classroom->schedules()->count() === 0) {
            return back()->with('error', 'Lớp chưa có lịch tuần để phát sinh buổi.');
        }

        // Lấy tham số tùy chọn
        $fromDate    = $request->input('from_date');       // YYYY-MM-DD hoặc null
        $maxSessions = $request->filled('max_sessions') ? (int)$request->input('max_sessions') : null;
        $reset       = $request->boolean('reset', false);

        // Dispatch vào queue "sessions"
        GenerateSessionsForClass::dispatch(
            $classroom->id,
            $fromDate,
            $maxSessions,
            $reset
        )->onQueue('sessions');

        return back()->with('success', 'Đã đưa yêu cầu phát sinh buổi vào hàng đợi.');
    }

    /**
     * Danh sách buổi theo lớp (cơ bản). Sẽ gắn UI ở bước kế tiếp.
     */
    public function index(Request $request, Classroom $classroom)
{
    $query = ClassSession::query()
        ->where('class_id', $classroom->id)
        ->with([
            'room:id,code,name',
            'substitution' => function($q) {
                $q->with('substituteTeacher:id,name');
            }
        ]);

    $sort  = $request->string('sort')->toString() ?: 'date';
    $order = $request->string('order')->toString() === 'desc' ? 'desc' : 'asc';
    if (!in_array($sort, ['date','start_time','end_time','id','status','room'])) {
        $sort = 'date';
    }

    if ($sort === 'room') {
        // Sắp xếp theo phòng: ưu tiên code, sau đó name. Dùng left join để giữ những buổi chưa có phòng.
        $query->leftJoin('rooms as r', 'r.id', '=', 'class_sessions.room_id')
              ->select('class_sessions.*')
              ->orderByRaw("COALESCE(r.code, '') $order")
              ->orderByRaw("COALESCE(r.name, '') $order")
              ->orderBy('start_time', $order);
    } else {
        $query->orderBy($sort, $order)->orderBy('start_time', $order);
    }

    $perPage  = (int) ($request->integer('per_page') ?: 20);
    $sessions = $query->paginate($perPage)->withQueryString();

    // Thêm cờ is_holiday cho từng session
    $sessions->getCollection()->transform(function ($session) use ($classroom) {
        $session->is_holiday = $this->holidayService->isHoliday($classroom->id, $classroom->branch_id, $session->date);
        return $session;
    });

    // NEW: danh sách phòng cùng chi nhánh của lớp
    $rooms = Room::query()
        ->where('branch_id', $classroom->branch_id)
        ->orderBy('name')
        ->get(['id','code','name'])
        ->map(fn($r) => [
            'id'   => $r->id,
            'code' => $r->code,
            'name' => $r->name,
            'label'=> trim(($r->code ? $r->code.' - ' : '').$r->name),
            'value'=> (string)$r->id,
        ]);

    // NEW: danh sách giáo viên (có thể lọc theo chi nhánh hoặc tất cả)
    $teachers = User::query()
        ->whereHas('roles', fn($q) => $q->where('name', 'teacher'))
        ->where('active', true)
        ->orderBy('name')
        ->get(['id','name'])
        ->map(fn($t) => [
            'id'    => $t->id,
            'name'  => $t->name,
            'label' => $t->name,
            'value' => (string)$t->id,
        ]);

    return Inertia::render('Manager/Classrooms/Sessions/Index', [
        'classroom' => $classroom->only(['id','code','name','branch_id']),
        'sessions'  => $sessions,
        'rooms'     => $rooms, // <-- thêm
        'teachers'  => $teachers, // <-- thêm
        'filters'   => [
            'sort'    => $sort,
            'order'   => $order,
            'perPage' => $perPage,
        ],
    ]);
}

    /**
     * Cập nhật 1 buổi (đổi giờ/phòng/trạng thái/ghi chú).
     * Trùng phòng sẽ bị chặn ở UpdateSessionRequest.
     */
    public function update(UpdateSessionRequest $request, Classroom $classroom, ClassSession $session)
    {
        // đảm bảo session thuộc về classroom
        if ((int) $session->class_id !== (int) $classroom->id) {
            abort(404);
        }

        $data = $request->validated();
        // Chặn ngày nghỉ
        if (isset($data['date']) && $this->holidayService->isHoliday($classroom->id, $classroom->branch_id, $data['date'])) {
            return back()->withErrors(['date' => 'Ngày này trùng kỳ nghỉ. Vui lòng chọn ngày khác.'])->withInput();
        }

        // nếu FE gửi HH:mm, DB cột time sẽ tự parse OK
        $session->update($data);

        return back()->with('success', 'Đã cập nhật buổi học.');
    }

    public function store(StoreSessionRequest $request, Classroom $classroom)
    {
        $data = $request->validated();

        // Chặn ngày nghỉ
        if ($this->holidayService->isHoliday($classroom->id, $classroom->branch_id, $data['date'])) {
            return back()->withErrors(['date' => 'Ngày này trùng kỳ nghỉ. Vui lòng chọn ngày khác.'])->withInput();
        }

        // session_no = max + 1 theo lớp
        $nextNo = (int) (ClassSession::where('class_id', $classroom->id)->max('session_no') ?? 0) + 1;

        ClassSession::create([
            'class_id'    => $classroom->id,
            'session_no'  => $nextNo,
            'date'        => $data['date'],
            'start_time'  => $data['start_time'],
            'end_time'    => $data['end_time'],
            'room_id'     => $data['room_id'] ?? null,
            'status'      => $data['status'] ?? 'planned',
            'note'        => $data['note'] ?? null,
        ]);

        return back()->with('success', 'Đã tạo buổi học.');
    }

    /**
     * Week View cho buổi học của 1 lớp.
     * Query:
     *  - date: ngày bất kỳ trong tuần muốn xem (mặc định: hôm nay)
     *  - room_id: lọc theo phòng (optional)
     */
    public function week(Request $request, Classroom $classroom)
    {
        // Ngày tham chiếu (y-m-d), mặc định hôm nay
        $ref = $request->date
            ? Carbon::parse($request->date)->startOfDay()
            : now()->startOfDay();

        // Chuẩn hóa: tuần bắt đầu Thứ 2 (ISO-8601)
        $weekStart = $ref->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $roomId = $request->integer('room_id') ?: null;

        // Danh sách phòng cùng chi nhánh của lớp (để filter)
        $rooms = Room::query()
            ->where('branch_id', $classroom->branch_id)
            ->orderBy('name')
            ->get(['id','code','name'])
            ->map(fn($r) => [
                'id'    => $r->id,
                'code'  => $r->code,
                'name'  => $r->name,
                'label' => trim(($r->code ? $r->code.' - ' : '').$r->name),
                'value' => (string)$r->id,
            ]);

        // Lấy các buổi trong tuần, có thể lọc theo phòng
        $sessions = ClassSession::query()
            ->where('class_id', $classroom->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when($roomId, fn($q) => $q->where('room_id', $roomId))
            ->with(['room:id,code,name'])
            ->orderBy('date')
            ->orderBy('start_time')
            ->get([
                'id','class_id','date','start_time','end_time','room_id','status','session_no','note'
            ]);

        // Gom theo ngày để render nhanh ở FE
        $byDay = $sessions->groupBy('date')->map(function ($items) {
            return $items->values();
        });

        return Inertia::render('Manager/Classrooms/Sessions/Week', [
            'classroom' => $classroom->only(['id','code','name','branch_id']),
            'filters'   => [
                'date'    => $ref->toDateString(),
                'room_id' => $roomId,
            ],
            'week' => [
                'start' => $weekStart->toDateString(),
                'end'   => $weekEnd->toDateString(),
                'days'  => collect(range(0,6))->map(fn($i) => $weekStart->copy()->addDays($i)->toDateString()),
            ],
            'rooms' => $rooms,
            'sessionsByDay' => $byDay, // { '2025-08-18': [ ... ], ... }
        ]);
    }

    public function bulkAssignRoom(Request $request, Classroom $classroom)
    {
        $data = $request->validate([
            'ids' => ['required','array','min:1'],
            'ids.*' => ['integer','exists:class_sessions,id'],
            'room_id' => ['required','integer','exists:rooms,id'],
        ], [], [
            'ids' => 'các buổi',
            'room_id' => 'phòng',
        ]);

        $ids = $data['ids'];
        $roomId = $data['room_id'];

        // Gán phòng theo từng buổi + check trùng giờ trong ngày (dựa trên scopeOverlapping bạn đã có)
        $updated = 0; $conflicts = [];
        $sessions = ClassSession::where('class_id', $classroom->id)->whereIn('id', $ids)->get();

        foreach ($sessions as $s) {
            $overlap = ClassSession::overlapping([
                'room_id' => $roomId,
                'date' => $s->date,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ])->where('id','!=',$s->id)->exists();

            if ($overlap) {
                $conflicts[] = $s->id;
                continue;
            }

            $s->update(['room_id' => $roomId]);
            $updated++;
        }

        if (count($conflicts) > 0) {
            return back()->with(
                'warning',
                'Đã gán phòng cho ' . $updated . ' buổi. ' . count($conflicts) . ' buổi bị trùng lịch và không được cập nhật.'
            );
        }

        return back()->with('success', 'Đã gán phòng cho ' . $updated . ' buổi.');
    }

}

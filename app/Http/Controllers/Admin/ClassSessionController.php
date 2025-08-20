<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSessionsRequest;
use App\Jobs\GenerateSessionsForClass;
use App\Models\Classroom;
use App\Http\Requests\UpdateSessionRequest;
use App\Models\ClassSession;
use App\Models\Room;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClassSessionController extends Controller
{
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
        ->with(['room:id,code,name']);

    $sort  = $request->string('sort')->toString() ?: 'date';
    $order = $request->string('order')->toString() === 'desc' ? 'desc' : 'asc';
    if (!in_array($sort, ['date','start_time','end_time','session_no','status'])) {
        $sort = 'date';
    }
    $query->orderBy($sort, $order)->orderBy('start_time', $order);

    $perPage  = (int) ($request->integer('per_page') ?: 20);
    $sessions = $query->paginate($perPage)->withQueryString();

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

    return Inertia::render('Admin/Classrooms/Sessions/Index', [
        'classroom' => $classroom->only(['id','code','name','branch_id']),
        'sessions'  => $sessions,
        'rooms'     => $rooms, // <-- thêm
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

        // chỉ cho phép update các trường này
        $session->fill([
            'date'       => $data['date']       ?? $session->date,
            'start_time' => $data['start_time'] ?? $session->start_time,
            'end_time'   => $data['end_time']   ?? $session->end_time,
            'room_id'    => array_key_exists('room_id',$data) ? $data['room_id'] : $session->room_id,
            'status'     => $data['status']     ?? $session->status,
            'note'       => $data['note']       ?? $session->note,
        ])->save();

        return back()->with('success', 'Đã cập nhật buổi học.');
    }
}

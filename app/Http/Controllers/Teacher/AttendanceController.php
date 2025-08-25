<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttendanceRequest;
use App\Models\ClassSession;
use App\Models\Enrollment;
use App\Models\TeacherTimesheet;
use App\Models\TeachingAssignment;
use App\Models\Attendance; // bảng attendances: class_session_id, student_id, status
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    // Danh sách buổi của giáo viên (bản tối giản: lấy buổi thuộc các lớp có teacher_id = tôi)
    public function index(Request $request)
    {
        $teacherId = Auth::id();

        $sessions = ClassSession::query()
            ->with(['classroom:id,code,name'])
            ->whereHas('classroom', fn($q) => $q->where('teacher_id', $teacherId))
            ->orderBy('date', 'asc')
            ->orderBy('start_time', 'asc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Teacher/Attendance/Index', [
            'sessions' => $sessions,
        ]);
    }

    // Màn phiếu điểm danh
    public function show(Request $request, ClassSession $session)
    {
        // (tối giản) quyền: phiên thuộc về lớp mà tôi là teacher
        $this->authorizeSession($session);

        // Lấy danh sách học viên đã ghi danh lớp
        $enrollments = Enrollment::query()
            ->with(['student:id,code,name,phone,email'])
            ->where('class_id', $session->class_id)
            ->orderBy('id')
            ->get(['id','student_id','start_session_no']);

        // Lấy điểm danh đã có
        $existing = Attendance::where('class_session_id', $session->id)
            ->get(['student_id','status'])
            ->keyBy('student_id');

        // Map ra list cho FE
        $rows = $enrollments->map(function ($en) use ($existing) {
            $s = $en->student;
            return [
                'student_id' => $s->id,
                'code'       => $s->code,
                'name'       => $s->name,
                'phone'      => $s->phone,
                'email'      => $s->email,
                'status'     => optional($existing->get($s->id))->status ?? 'present',
            ];
        });

        return Inertia::render('Teacher/Attendance/Session', [
            'session' => [
                'id'          => $session->id,
                'class_id'    => $session->class_id,
                'date'        => $session->date,
                'start_time'  => $session->start_time,
                'end_time'    => $session->end_time,
            ],
            'classroom' => [
                'id'   => $session->classroom->id ?? null,
                'code' => $session->classroom->code ?? null,
                'name' => $session->classroom->name ?? null,
            ],
            'students' => $rows,
        ]);
    }

    // Lưu phiếu điểm danh
    public function store(StoreAttendanceRequest $request, ClassSession $session)
    {
        $validated = $request->validated();
        $user = $request->user();

        // Chỉ nhận học viên đã ghi danh
        $enrolledIds = Enrollment::where('class_id', $session->class_id)->pluck('student_id')->all();

        $inputRows = collect($validated['items'] ?? []);
        // Lọc đúng keys và chỉ giữ học viên đã ghi danh
        $targets = $inputRows->filter(function ($r) use ($enrolledIds) {
            // Bảo vệ key; phòng FE gửi sai khóa
            return isset($r['student_id'], $r['status']) && in_array((int)$r['student_id'], $enrolledIds, true);
        })->values();

        $skipped = $inputRows->count() - $targets->count();

        DB::transaction(function () use ($targets, $session, $user) {
            // Chuẩn bị dữ liệu upsert
            $now = now();
            $rows = $targets->map(function ($r) use ($session, $now) {
                return [
                    'class_session_id' => $session->id,
                    'student_id'       => (int) $r['student_id'],
                    'status'           => (string) $r['status'], // present|absent|late|excused
                    'note'             => $r['note'] ?? null,
                    'updated_at'       => $now,
                    'created_at'       => $now,
                ];
            })->all();

            if (!empty($rows)) {
                // MySQL 8: UPDATE status, note, updated_at nếu đã tồn tại (unique: class_session_id + student_id)
                Attendance::upsert(
                    $rows,
                    ['class_session_id', 'student_id'],
                    ['status', 'note', 'updated_at']
                );
            }

            // Timesheet nháp cho GV (không đổi status của session)
            TeacherTimesheet::updateOrCreate(
                [
                    'class_session_id' => $session->id,
                    'teacher_id'       => $user->id,
                ],
                [
                    'amount' => $this->calcAmountFor($session, $user->id),
                    'status' => 'draft',
                ]
            );

            $session->touch();
        });

        $count = $targets->count();
        $msg   = "Đã lưu điểm danh {$count} học viên" . ($skipped > 0 ? "; bỏ qua {$skipped} học viên chưa ghi danh." : '.');

        return back()->with('success', $msg);
    }


    /**
     * Tính tiền buổi cho giáo viên (đơn giản theo TeachingAssignment mới nhất).
     * Bạn có thể thay bằng service chuyên biệt.
     */
    private function calcAmountFor(ClassSession $session, int $teacherId): int
    {
        return (int) TeachingAssignment::where('class_id', $session->class_id)
            ->where('teacher_id', $teacherId)
            ->orderByDesc('effective_from')
            ->value('rate_per_session') ?? 0;
    }

    private function authorizeSession(ClassSession $session): void
    {
        $teacherId = Auth::id();

        // Tối giản: buổi thuộc lớp mà tôi là teacher
        $belongs = optional($session->classroom)->teacher_id === $teacherId;

        // TODO (bước sau): tính cả dạy thay qua bảng session_substitutions
        if (!$belongs) {
            abort(403, 'Bạn không có quyền điểm danh buổi này.');
        }
    }
}

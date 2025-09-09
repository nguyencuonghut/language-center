<?php

namespace App\Http\Controllers\Manager;

use App\Http\Requests\SessionSubstitution\StoreSessionSubstitutionRequest;
use App\Http\Requests\SessionSubstitution\UpdateSessionSubstitutionRequest;
use App\Models\ClassSession;
use App\Models\SessionSubstitution;
use App\Models\TeacherTimesheet;
use App\Models\TeachingAssignment;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionSubstitutionController extends Controller
{
    /**
     * Tạo dạy thay cho 1 buổi.
     */
    public function store(StoreSessionSubstitutionRequest $request, $classroom, $class_session)
    {
        $data = $request->validated();

        $classSession = ClassSession::findOrFail($class_session);

        return DB::transaction(function () use ($classSession, $data) {
            // Chỉ cho phép 1 substitution/1 session (nếu bạn muốn nhiều, bỏ đoạn check này)
            $already = SessionSubstitution::where('class_session_id', $classSession->id)->first();
            if ($already) {
                return back()->with('error', 'Buổi này đã có giáo viên dạy thay.');
            }

            // Tạo bản ghi dạy thay
            $sub = SessionSubstitution::create([
                'class_session_id'      => $classSession->id,
                'substitute_teacher_id' => $data['substitute_teacher_id'],
                'rate_override'         => $data['rate_override'] ?? null,
                'reason'                => $data['reason'] ?? null,
            ]);

            // Cập nhật (hoặc tạo) timesheet theo GV dạy thay
            $amount = $this->calcAmountFor($classSession, $data['substitute_teacher_id'], $data['rate_override'] ?? null);

            // Xoá timesheet cũ của GV chính (nếu có), để tránh trùng (unique class_session_id + teacher_id)
            TeacherTimesheet::where('class_session_id', $classSession->id)->delete();

            // Tạo timesheet draft cho GV dạy thay
            TeacherTimesheet::updateOrCreate(
                ['class_session_id' => $classSession->id, 'teacher_id' => $data['substitute_teacher_id']],
                ['amount' => $amount, 'status' => 'draft']
            );

            return back()->with('success', 'Đã gán giáo viên dạy thay.');
        });
    }

    /**
     * Cập nhật dạy thay (đổi GV hoặc rate_override, reason).
     */
    public function update(UpdateSessionSubstitutionRequest $request, $classroom, $class_session, SessionSubstitution $substitution)
    {
        $data = $request->validated();

        $classSession = ClassSession::findOrFail($class_session);

        if ($substitution->class_session_id !== $classSession->id) {
            abort(404);
        }

        return DB::transaction(function () use ($classSession, $substitution, $data) {
            // Cập nhật substitution
            $substitution->update([
                'substitute_teacher_id' => $data['substitute_teacher_id'],
                'rate_override'         => $data['rate_override'] ?? null,
                'reason'                => $data['reason'] ?? null,
            ]);

            // Làm mới timesheet tương ứng
            TeacherTimesheet::where('class_session_id', $classSession->id)->delete();

            $amount = $this->calcAmountFor($classSession, $data['substitute_teacher_id'], $data['rate_override'] ?? null);

            TeacherTimesheet::updateOrCreate(
                ['class_session_id' => $classSession->id, 'teacher_id' => $data['substitute_teacher_id']],
                ['amount' => $amount, 'status' => 'draft']
            );

            return back()->with('success', 'Đã cập nhật dạy thay.');
        });
    }

    /**
     * Huỷ dạy thay → trả timesheet về GV gốc theo TeachingAssignment (nếu có).
     */
    public function destroy(Request $request, $classroom, $class_session, SessionSubstitution $substitution)
    {
        $classSession = ClassSession::findOrFail($class_session);

        if ($substitution->class_session_id !== $classSession->id) {
            abort(404);
        }

        return DB::transaction(function () use ($classSession, $substitution) {
            $substitution->delete();

            // Xoá timesheet của GV dạy thay
            TeacherTimesheet::where('class_session_id', $classSession->id)->delete();

            // Tìm GV gốc theo TeachingAssignment (effective gần nhất trước hoặc bằng ngày buổi học)
            $teacherId = $this->findAssignedTeacherId($classSession);

            if ($teacherId) {
                $amount = $this->calcAmountFor($classSession, $teacherId, null);
                TeacherTimesheet::updateOrCreate(
                    ['class_session_id' => $classSession->id, 'teacher_id' => $teacherId],
                    ['amount' => $amount, 'status' => 'draft']
                );
            }

            return back()->with('success', 'Đã huỷ dạy thay.');
        });
    }

    /**
     * Tính tiền buổi cho GV: ưu tiên rate_override, nếu không có thì lấy rate theo TeachingAssignment hiệu lực.
     */
    private function calcAmountFor(ClassSession $classSession, int $teacherId, ?int $rateOverride): int
    {
        if ($rateOverride !== null) {
            return (int) $rateOverride;
        }

        $rate = TeachingAssignment::where('class_id', $classSession->class_id)
            ->where('teacher_id', $teacherId)
            ->when($classSession->date, fn($q) => $q->where(function ($qq) use ($classSession) {
                $qq->whereNull('effective_from')->orWhere('effective_from', '<=', $classSession->date);
            }))
            ->orderByDesc('effective_from')
            ->value('rate_per_session');

        return (int) ($rate ?? 0);
    }

    /**
     * Tìm GV “gốc” của lớp vào ngày diễn ra buổi.
     */
    private function findAssignedTeacherId(ClassSession $classSession): ?int
    {
        return TeachingAssignment::where('class_id', $classSession->class_id)
            ->when($classSession->date, fn($q) => $q->where(function ($qq) use ($classSession) {
                $qq->whereNull('effective_from')->orWhere('effective_from', '<=', $classSession->date);
            }))
            ->orderByDesc('effective_from')
            ->value('teacher_id');
    }
}

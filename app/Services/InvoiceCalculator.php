<?php

namespace App\Services;

use App\Models\Classroom;
use App\Models\Enrollment;

class InvoiceCalculator
{
    public function tuitionDefaultTotal(int $classId, ?int $studentId = null, ?int $startSessionNo = null): int
    {
        /** @var Classroom $class */
        $class = Classroom::findOrFail($classId);

        // Lấy start_session_no từ enrollment nếu có
        if ($studentId && !$startSessionNo) {
            $startSessionNo = (int) Enrollment::where('class_id', $classId)
                ->where('student_id', $studentId)
                ->value('start_session_no') ?: 1;
        }
        $startSessionNo = max(1, (int) ($startSessionNo ?: 1));

        $sessionsTotal      = (int) $class->sessions_total;
        $remainingSessions  = max(0, $sessionsTotal - ($startSessionNo - 1));

        // Đơn giá 1 buổi (làm tròn xuống để an toàn tiền tệ)
        $perSession = (int) floor(((int) $class->tuition_fee) / max(1, $sessionsTotal));

        return (int) ($remainingSessions * $perSession);
    }
}

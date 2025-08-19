<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSessionsRequest;
use App\Jobs\GenerateSessionsForClass;
use App\Models\Classroom;

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
}

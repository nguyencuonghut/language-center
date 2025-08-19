<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateSessionsRequest;
use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ClassSessionController extends Controller
{
    /**
     * Phát sinh các buổi (class_sessions) theo lịch tuần của lớp.
     * - Duyệt từ from_date (hoặc start_date của lớp) tới khi đủ max_sessions.
     * - Tạo session_no liên tục, status = planned, room_id = null.
     * - Nếu 'reset' = true: xoá các buổi planned hiện có của lớp trước khi generate.
     */
    public function generate(GenerateSessionsRequest $request, Classroom $classroom)
    {
        $fromDate = $request->input('from_date')
            ? Carbon::parse($request->input('from_date'))->startOfDay()
            : Carbon::parse($classroom->start_date)->startOfDay();

        // lịch tuần của lớp
        $schedules = ClassSchedule::where('class_id', $classroom->id)->get();
        $count = ClassSchedule::where('class_id', $classroom->id)->count();
        Log::info('Generate sessions', ['class_id' => $classroom->id, 'schedules_count' => $count]);
        if ($schedules->isEmpty()) {
            return back()->with('error', 'Lớp chưa có lịch tuần để phát sinh buổi.');
        }

        // reset planned (tuỳ chọn)
        if ($request->boolean('reset')) {
            ClassSession::where('class_id', $classroom->id)
                ->where('status', 'planned')
                ->delete();
        }

        $existingCount = ClassSession::where('class_id', $classroom->id)->count();
        $targetTotal   = $classroom->sessions_total ?? 0;

        $remain = $request->filled('max_sessions')
            ? (int) $request->integer('max_sessions')
            : max(0, $targetTotal - $existingCount);

        if ($remain <= 0) {
            return back()->with('info', 'Không còn buổi cần phát sinh.');
        }

        $byWeekday = $schedules->groupBy('weekday'); // 0..6
        $created   = 0;
        $cursor    = $fromDate->copy();

        DB::transaction(function () use ($classroom, $byWeekday, &$created, $remain, $cursor) {
            // session_no hiện tại (tiếp nối)
            $nextNo = (int) ClassSession::where('class_id', $classroom->id)->max('session_no');
            $nextNo = $nextNo ? $nextNo + 1 : 1;

            while ($created < $remain) {
                $w = (int) $cursor->format('w'); // 0..6
                if ($byWeekday->has($w)) {
                    foreach ($byWeekday[$w] as $slot) {
                        if ($created >= $remain) break;

                        ClassSession::create([
                            'class_id'   => $classroom->id,
                            'session_no' => $nextNo++,
                            'date'       => $cursor->toDateString(),
                            'start_time' => $slot->start_time, // theo lịch tuần
                            'end_time'   => $slot->end_time,
                            'room_id'    => null,
                            'status'     => 'planned',
                            'note'       => null,
                        ]);
                        $created++;
                    }
                }
                $cursor->addDay();
            }
        });

        return back()->with('success', "Đã phát sinh {$created} buổi học.");
    }
}

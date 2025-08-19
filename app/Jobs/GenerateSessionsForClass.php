<?php

namespace App\Jobs;

use App\Models\Classroom;
use App\Models\ClassSchedule;
use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GenerateSessionsForClass implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int */
    public int $classroomId;

    /** @var string|null YYYY-MM-DD */
    public ?string $fromDate;

    /** @var int|null */
    public ?int $maxSessions;

    /** @var bool */
    public bool $resetPlanned;

    /**
     * @param int         $classroomId   ID lớp
     * @param string|null $fromDate      Ngày bắt đầu phát sinh (YYYY-MM-DD). Null => dùng start_date của lớp
     * @param int|null    $maxSessions   Số buổi cần phát sinh. Null => (sessions_total - đã có)
     * @param bool        $resetPlanned  Xoá các buổi 'planned' cũ trước khi phát sinh
     */
    public function __construct(int $classroomId, ?string $fromDate = null, ?int $maxSessions = null, bool $resetPlanned = false)
    {
        $this->classroomId  = $classroomId;
        $this->fromDate     = $fromDate;
        $this->maxSessions  = $maxSessions;
        $this->resetPlanned = $resetPlanned;

        // (tuỳ chọn) gán queue riêng
        $this->onQueue('sessions');
    }

    /**
     * Tránh job trùng trên cùng 1 lớp
     */
    public function middleware(): array
    {
        // Khoá theo classroomId trong vòng 60s
        return [new WithoutOverlapping("gen-sessions:{$this->classroomId}")];
    }

    /**
     * Thực thi job
     */
    public function handle(): void
    {
        /** @var Classroom|null $classroom */
        $classroom = Classroom::find($this->classroomId);
        if (!$classroom) {
            // Không có lớp -> bỏ qua
            return;
        }

        // Lấy lịch tuần QUA QUAN HỆ để tránh scope/nhầm FK
        $schedules = $classroom->schedules()
            ->select('weekday', 'start_time', 'end_time')
            ->get();

        if ($schedules->isEmpty()) {
            // Lớp chưa có lịch tuần -> không làm gì
            return;
        }

        // Ngày bắt đầu
        $start = $this->fromDate
            ? Carbon::parse($this->fromDate)->startOfDay()
            : Carbon::parse($classroom->start_date)->startOfDay();

        // Xoá các buổi 'planned' cũ (tuỳ chọn)
        if ($this->resetPlanned) {
            ClassSession::where('class_id', $classroom->id)
                ->where('status', 'planned')
                ->delete();
        }

        // Tính số buổi cần phát sinh
        $existingCount = ClassSession::where('class_id', $classroom->id)->count();
        $targetTotal   = (int) ($classroom->sessions_total ?? 0);
        $remain        = $this->maxSessions ?? max(0, $targetTotal - $existingCount);

        if ($remain <= 0) {
            return; // không còn buổi cần phát sinh
        }

        $byWeekday = $schedules->groupBy('weekday'); // 0..6
        $created   = 0;
        $cursor    = $start->copy();

        DB::transaction(function () use ($classroom, $byWeekday, &$created, $remain, $cursor) {
            // Tiếp nối session_no hiện có
            $nextNo = (int) ClassSession::where('class_id', $classroom->id)->max('session_no');
            $nextNo = $nextNo ? $nextNo + 1 : 1;

            while ($created < $remain) {
                $w = (int) $cursor->format('w'); // 0..6 (CN..T7)
                if ($byWeekday->has($w)) {
                    foreach ($byWeekday[$w] as $slot) {
                        if ($created >= $remain) {
                            break;
                        }

                        ClassSession::create([
                            'class_id'   => $classroom->id,
                            'session_no' => $nextNo++,
                            'date'       => $cursor->toDateString(),
                            'start_time' => $slot->start_time,
                            'end_time'   => $slot->end_time,
                            'room_id'    => null,           // sẽ gán sau
                            'status'     => 'planned',      // planned -> taught/cancelled...
                            'note'       => null,
                        ]);
                        $created++;
                    }
                }
                $cursor->addDay();
            }
        });

        // (tuỳ chọn) ghi log hoạt động, sự kiện, v.v...
        // activity()->performedOn($classroom)->log("Generated {$created} sessions");
    }
}

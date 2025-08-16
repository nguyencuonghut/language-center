<?php

namespace Database\Seeders;

use App\Models\Attendance;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // Lấy các session trong 30 ngày gần đây dựa trên cột phổ biến
        $sessions = DB::table('class_sessions')
            ->select('id', 'class_id', 'starts_at', 'date', 'created_at')
            ->get()
            ->filter(function ($s) {
                $today = Carbon::now()->startOfDay();
                $sessionDate = null;

                if (Schema::hasColumn('class_sessions', 'date') && !empty($s->date)) {
                    $sessionDate = Carbon::parse($s->date)->startOfDay();
                } elseif (Schema::hasColumn('class_sessions', 'starts_at') && !empty($s->starts_at)) {
                    $sessionDate = Carbon::parse($s->starts_at)->startOfDay();
                } else {
                    $sessionDate = Carbon::parse($s->created_at)->startOfDay();
                }

                return $sessionDate->between($today->copy()->subDays(29), $today);
            });

        if ($sessions->isEmpty()) {
            $this->command?->warn('AttendanceSeeder: Không tìm thấy class_sessions trong 30 ngày gần đây.');
            return;
        }

        foreach ($sessions as $session) {
            // Cố gắng lấy học viên từ enrollments nếu có
            $studentIds = [];

            if (Schema::hasTable('enrollments')) {
                $enrollQ = DB::table('enrollments');
                if (Schema::hasColumn('enrollments', 'class_id') && isset($session->class_id)) {
                    $enrollQ->where('class_id', $session->class_id);
                }
                $studentIds = $enrollQ->pluck('student_id')->unique()->values()->all();
            }

            // Fallback nếu không có enrollments
            if (empty($studentIds)) {
                $studentIds = DB::table('students')->inRandomOrder()->limit(12)->pluck('id')->all();
            }

            if (empty($studentIds)) {
                continue;
            }

            // Tạo điểm danh cho 60–100% số học viên của session
            $take = max(1, (int)round(count($studentIds) * mt_rand(60, 100) / 100));
            $picked = collect($studentIds)->shuffle()->take($take);

            foreach ($picked as $sid) {
                // Tỷ lệ: present ~ 75%, absent ~ 12%, late ~ 10%, excused ~ 3%
                $r = mt_rand(1, 100);
                $status = 'present';
                if ($r <= 12) {
                    $status = 'absent';
                } elseif ($r <= 22) {
                    $status = 'late';
                } elseif ($r <= 25) {
                    $status = 'excused';
                }

                Attendance::updateOrCreate(
                    ['class_session_id' => $session->id, 'student_id' => $sid],
                    ['status' => $status, 'note' => null]
                );
            }
        }
    }
}

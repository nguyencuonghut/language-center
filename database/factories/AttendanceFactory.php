<?php

namespace Database\Factories;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $classSessionId = DB::table('class_sessions')->inRandomOrder()->value('id');
        $studentId      = DB::table('students')->inRandomOrder()->value('id');

        // fallback nếu chưa có dữ liệu
        if (!$classSessionId || !$studentId) {
            return [
                'class_session_id' => $classSessionId ?? 1,
                'student_id'       => $studentId ?? 1,
                'status'           => 'present',
                'note'             => null,
            ];
        }

        $status = $this->faker->randomElement(['present','absent','late','excused']);

        return [
            'class_session_id' => $classSessionId,
            'student_id'       => $studentId,
            'status'           => $status,
            'note'             => null,
        ];
    }
}

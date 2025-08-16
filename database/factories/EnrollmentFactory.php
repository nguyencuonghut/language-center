<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $classId = Classroom::inRandomOrder()->value('id');
        $startNo = $this->faker->randomElement([1,1,1,3,5]); // đa số vào từ đầu, một số vào muộn

        return [
            'student_id'       => Student::inRandomOrder()->value('id') ?? Student::factory(),
            'class_id'         => $classId ?? Classroom::factory(),
            'enrolled_at'      => $this->faker->date(),
            'start_session_no' => $startNo,
            'status'           => 'active',
        ];
    }
}

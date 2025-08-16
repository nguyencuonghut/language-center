<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $classId = Classroom::inRandomOrder()->first();
        $student = Student::inRandomOrder()->first();
        $branch  = Branch::inRandomOrder()->first();

        $total = $classId
            ? $classId->tuition_fee
            : $this->faker->numberBetween(1500000, 6000000);

        return [
            'branch_id' => $branch?->id ?? Branch::factory(),
            'student_id'=> $student?->id ?? Student::factory(),
            'class_id'  => $classId?->id,
            'total'     => $total,
            'status'    => $this->faker->randomElement(['unpaid','partial','paid']),
            'due_date'  => $this->faker->dateTimeBetween('now', '+20 days'),
        ];
    }
}

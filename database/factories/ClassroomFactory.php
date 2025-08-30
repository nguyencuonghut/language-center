<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classroom>
 */
class ClassroomFactory extends Factory
{
    protected $model = \App\Models\Classroom::class;

    public function definition(): array
    {
        $sessions = $this->faker->randomElement([16, 20, 24]);
        return [
            'code'           => strtoupper($this->faker->unique()->bothify('CL##??')),
            'name'           => $this->faker->randomElement(['Kids A','Kids B','Teens A','IELTS F']).' '.$this->faker->numberBetween(10,99),
            'term_code'      => 'K'.$this->faker->numberBetween(1,3),
            'course_id'      => Course::inRandomOrder()->value('id') ?? Course::factory(),
            'branch_id'      => Branch::inRandomOrder()->value('id') ?? Branch::factory(),
            'start_date'     => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'sessions_total' => $sessions,
            'tuition_fee'    => $sessions * $this->faker->randomElement([200000, 250000, 300000]),
            'status'         => 'open',
        ];
    }
}

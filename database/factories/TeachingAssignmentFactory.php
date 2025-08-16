<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TeachingAssignment>
 */
class TeachingAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'class_id'         => Classroom::inRandomOrder()->value('id') ?? Classroom::factory(),
            'teacher_id'       => User::role('teacher')->inRandomOrder()->value('id'),
            'rate_per_session' => $this->faker->randomElement([200000, 250000, 300000]),
            'effective_from'   => null,
            'effective_to'     => null,
        ];
    }
}

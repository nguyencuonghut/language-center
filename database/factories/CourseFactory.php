<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
         return [
            'code'     => strtoupper($this->faker->unique()->bothify('CRS##')),
            'name'     => $this->faker->randomElement(['Kids A','Kids B','Teens A','IELTS F','TOEIC']),
            'audience' => $this->faker->randomElement(['kids','student','working','toeic','ielts']),
            'language' => $this->faker->randomElement(['en','zh','ko','ja']),
            'active'   => true,
        ];
    }
}

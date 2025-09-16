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
            'audience' => $this->faker->randomElement(['Thiếu nhi','Sinh viên','Người đi làm','Người lớn']),
            'language' => $this->faker->randomElement(['Tiếng Anh','Tiếng Trung','Tiếng Hàn','Tiếng Nhật']),
            'active'   => true,
        ];
    }
}

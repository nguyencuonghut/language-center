<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code'    => 'STU'.$this->faker->unique()->numberBetween(100,999).$this->faker->randomElement(['A','B','C']),
            'name'    => $this->faker->name(),
            'gender'  => $this->faker->randomElement(['Nam','Nữ','Khác']),
            'dob'     => $this->faker->dateTimeBetween('-16 years', '-6 years'),
            'email'   => $this->faker->safeEmail(),
            'phone'   => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'active'  => true,
        ];
    }
}

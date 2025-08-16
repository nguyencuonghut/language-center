<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Branch;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::inRandomOrder()->first()->id ?? Branch::factory(),
            'code'      => 'R' . $this->faker->unique()->numberBetween(1, 200),
            'name'      => 'Phòng ' . $this->faker->bothify('??#'),
            'capacity'  => $this->faker->numberBetween(15, 40),
            'active'    => true,
        ];
    }
}

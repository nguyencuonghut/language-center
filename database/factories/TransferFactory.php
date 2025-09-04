<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'from_class_id' => Classroom::factory(),
            'to_class_id' => Classroom::factory(),
            'effective_date' => $this->faker->dateTimeBetween('-30 days', '+30 days')->format('Y-m-d'),
            'start_session_no' => $this->faker->numberBetween(1, 10),
            'reason' => $this->faker->optional()->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'status' => $this->faker->randomElement(['active', 'reverted', 'retargeted']),
            'created_by' => User::factory(),
            'processed_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'transfer_fee' => $this->faker->optional()->numberBetween(0, 1000000), // 0-1M VND
        ];
    }

    /**
     * Indicate that the transfer is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'processed_at' => now(),
            'reverted_at' => null,
            'reverted_by' => null,
            'retargeted_at' => null,
            'retargeted_by' => null,
            'retargeted_to_class_id' => null,
        ]);
    }

    /**
     * Indicate that the transfer is reverted.
     */
    public function reverted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'reverted',
            'reverted_at' => $this->faker->dateTimeBetween($attributes['processed_at'] ?? '-30 days', 'now'),
            'reverted_by' => User::factory(),
        ]);
    }

    /**
     * Indicate that the transfer is retargeted.
     */
    public function retargeted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'retargeted',
            'retargeted_to_class_id' => Classroom::factory(),
            'retargeted_at' => $this->faker->dateTimeBetween($attributes['processed_at'] ?? '-30 days', 'now'),
            'retargeted_by' => User::factory(),
        ]);
    }

    /**
     * Transfer with fee.
     */
    public function withFee(int $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'transfer_fee' => $amount ?? $this->faker->numberBetween(100000, 500000),
        ]);
    }

    /**
     * Transfer without fee.
     */
    public function withoutFee(): static
    {
        return $this->state(fn (array $attributes) => [
            'transfer_fee' => 0,
        ]);
    }
}

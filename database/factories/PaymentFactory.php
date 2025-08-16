<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoice = Invoice::inRandomOrder()->first() ?? Invoice::factory()->create();
        $amount  = $this->faker->randomElement([500000, 1000000, 1500000, $invoice->total]);

        return [
            'invoice_id' => $invoice->id,
            'method'     => $this->faker->randomElement(['cash','bank','momo']),
            'paid_at'    => $this->faker->dateTimeBetween('-10 days', 'now'),
            'amount'     => $amount,
            'ref_no'     => strtoupper($this->faker->bothify('PMT###??')),
        ];
    }
}

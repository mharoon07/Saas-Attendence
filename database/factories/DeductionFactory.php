<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Deduction>
 */
class DeductionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_items' => [
                ['name' => 'Tax', 'amount' => $this->faker->randomFloat(2, 50, 500)],
                ['name' => 'Insurance', 'amount' => $this->faker->randomFloat(2, 50, 200)]
            ],
            "income_tax" => 0,
            "undertime" => 0,
            "negative_hour_rate" => 0,
            "loan_deduction" => 0,
            "advance_payment_deduction" => 0,
            "due_date" => $this->faker->dateTimeBetween("-1 years", "now")->format("Y-m-d"),
            "status" => $this->faker->boolean,
        ];
    }
}

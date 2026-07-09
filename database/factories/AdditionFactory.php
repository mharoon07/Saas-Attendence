<?php

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Addition>
 */
class AdditionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
//        return [
//            'rewards' => 0,
//            'incentives' => 0,
//            'reimbursements' => 0,
//            'shift_differentials' => 0,
//            'commissions' => 0,
//            'due_date' => Carbon::now()->toDateString(),
//            'status' => false,
//            "overtime" => 0,
//        ];
        return [
            'custom_items' => [
                ['name' => 'Bonus', 'amount' => $this->faker->randomFloat(2, 50, 500)],
                ['name' => 'Transport Allowance', 'amount' => $this->faker->randomFloat(2, 50, 200)]
            ],
            'due_date' => $this->faker->dateTimeBetween('-1 years', 'now')->format('Y-m-d'),
            'status' => $this->faker->boolean,
            "overtime" => 0,
            "extra_hour_rate" => 0,
        ];
    }
}

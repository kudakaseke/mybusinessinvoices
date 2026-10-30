<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'merchant' => fake()->company(),
            'description' => 'Office supplies',
            'category' => 'Office',
            'expense_date' => '2026-10-01',
            'currency' => 'USD',
            'amount' => '10.00',
        ];
    }
}

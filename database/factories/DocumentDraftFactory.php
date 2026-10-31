<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentDraftFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'key' => (string) Str::uuid(), 'kind' => 'invoices', 'payload' => ['customer_name' => fake()->name()]];
    }
}

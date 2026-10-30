<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'quotation_number' => fake()->unique()->numerify('DOC-####'),
            'issue_date' => '2026-10-01',
            'due_date' => '2026-10-31',
            'business_name' => 'Example Business',
            'business_email' => 'business@example.com',
            'customer_name' => 'Example Client',
            'customer_email' => 'client@example.com',
            'currency' => 'USD',
            'subtotal' => '100.00',
            'vat_rate' => 0,
            'discount_rate' => 0,
            'vat_amount' => 0,
            'discount_amount' => 0,
            'total' => '100.00',
            'notes' => null,
        ];
    }
}

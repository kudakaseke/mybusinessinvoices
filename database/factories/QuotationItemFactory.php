<?php

namespace Database\Factories;

use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuotationItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'product_id' => null,
            'description' => 'Consulting',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'amount' => '100.00',
        ];
    }
}

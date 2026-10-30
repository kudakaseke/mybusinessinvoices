<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'description' => 'Consulting',
            'quantity' => '1.00',
            'unit_price' => '100.00',
            'amount' => '100.00',
        ];
    }
}

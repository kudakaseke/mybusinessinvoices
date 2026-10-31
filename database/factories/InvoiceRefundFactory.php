<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InvoiceRefundFactory extends Factory
{
    public function definition(): array
    {
        return ['invoice_id' => Invoice::factory(), 'amount' => '10.00', 'refunded_on' => today()->toDateString(), 'reason' => 'Return', 'submission_key' => (string) Str::uuid()];
    }
}

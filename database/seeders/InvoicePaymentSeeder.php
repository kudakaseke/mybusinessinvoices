<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Database\Seeder;

class InvoicePaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'partial', 'total' => '100.00']);
        InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00', 'reference' => 'Sample partial payment']);
    }
}

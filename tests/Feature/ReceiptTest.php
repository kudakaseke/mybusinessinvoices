<?php

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Str;

test('recorded payments appear as downloadable receipts', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['invoice_number' => 'INV-RECEIPT', 'customer_name' => 'Receipt Client', 'issue_date' => today()->subDay(), 'total' => '100.00', 'status' => 'outstanding']);
    $this->actingAs($user)->get('/receipts')->assertOk()->assertSee('No receipts yet');
    $this->patch(route('invoices.payment', $invoice->id), ['status' => 'paid', 'amount' => '25.00', 'paid_on' => today()->toDateString(), 'reference' => 'BANK123', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    $payment = $invoice->payments()->firstOrFail();
    $this->get('/receipts')->assertOk()->assertSee('RCT-'.$payment->id)->assertSee('Receipt Client')->assertSee('INV-RECEIPT')->assertSee('25.00')->assertSee(route('payments.receipt', [$invoice->id, $payment->id]));
    $this->get(route('payments.receipt', [$invoice->id, $payment->id]))->assertOk()->assertDownload('Receipt-'.$payment->id.'.pdf')->assertSee('(RECEIPT)', false)->assertSee('25.00', false)->assertSee('BANK123', false);
});

test('receipt list and downloads exclude other businesses and reversed payments', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_name' => 'Owner Client']);
    $active = InvoicePayment::factory()->for($invoice)->create();
    $reversed = InvoicePayment::factory()->for($invoice)->create();
    $reversed->delete();
    $otherInvoice = Invoice::factory()->create(['customer_name' => 'Private Client']);
    $otherPayment = InvoicePayment::factory()->for($otherInvoice)->create();
    $this->get('/receipts')->assertRedirect(route('login'));
    $this->actingAs($user)->get('/receipts')->assertOk()->assertSee('RCT-'.$active->id)->assertDontSee('RCT-'.$reversed->id)->assertDontSee('Private Client');
    $this->get(route('payments.receipt', [$otherInvoice->id, $otherPayment->id]))->assertNotFound();
    $this->get(route('payments.receipt', [$invoice->id, $reversed->id]))->assertNotFound();
});

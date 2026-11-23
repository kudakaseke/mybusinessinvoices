<?php

use App\Mail\BusinessDocumentMail;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;

test('recorded payments appear as downloadable receipts', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['invoice_number' => 'INV-RECEIPT', 'customer_name' => 'Receipt Client', 'issue_date' => today()->subDay(), 'total' => '100.00', 'status' => 'outstanding']);
    $this->actingAs($user)->get('/receipts')->assertOk()->assertSee('No receipts yet');
    $this->patch(route('invoices.payment', $invoice->id), ['status' => 'paid', 'amount' => '25.00', 'paid_on' => today()->toDateString(), 'reference' => 'BANK123', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    $payment = $invoice->payments()->firstOrFail();
    $this->get('/receipts')->assertOk()->assertSee('RCT-'.$payment->id)->assertSee('Receipt Client')->assertSee('INV-RECEIPT')->assertSee('25.00')->assertSee(route('receipts.show', $payment->id));
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

test('receipt view offers a PDF preview and sharing actions', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'customer_phone' => '+263771234567']);
    $payment = InvoicePayment::factory()->for($invoice)->create();
    $this->actingAs($user)->get(route('receipts.show', $payment->id))->assertOk()
        ->assertSee('Receipt PDF preview')->assertSee('data:application/pdf;base64,', false)
        ->assertSee(route('payments.receipt', [$invoice->id, $payment->id]))->assertSee(route('receipts.email', $payment->id))
        ->assertSee('wa.me/263771234567')->assertSee('attach the downloaded PDF');
});

test('receipt emails include the PDF and use the customer address', function () {
    Mail::fake();
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com']);
    $payment = InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00']);
    $this->actingAs($user)->from(route('receipts.show', $payment->id))->post(route('receipts.email', $payment->id))
        ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('message');
    Mail::assertSent(BusinessDocumentMail::class, function ($mail) use ($payment) {
        return $mail->hasTo('client@example.com') && $mail->documentLabel === 'Receipt'
            && $mail->filename === 'Receipt-'.$payment->id.'.pdf' && str_starts_with($mail->pdf, '%PDF-')
            && $mail->amount === '25.00';
    });
    $mail = Mail::sent(BusinessDocumentMail::class)->first();
    $mail->assertSeeInHtml('Amount received')->assertDontSeeInHtml('Quoted total');
});

test('receipt viewing and emailing enforce ownership and exclude reversed payments', function () {
    Mail::fake();
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    $reversed = InvoicePayment::factory()->for($invoice)->create();
    $reversed->delete();
    $other = InvoicePayment::factory()->create();
    $this->get(route('receipts.show', $other->id))->assertRedirect(route('login'));
    $this->actingAs($user)->get(route('receipts.show', $other->id))->assertNotFound();
    $this->post(route('receipts.email', $other->id))->assertNotFound();
    $this->get(route('receipts.show', $reversed->id))->assertNotFound();
    $this->post(route('receipts.email', $reversed->id))->assertNotFound();
    Mail::assertNothingSent();
});

test('a receipt without a valid customer email cannot be emailed', function () {
    Mail::fake();
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_email' => 'invalid']);
    $payment = InvoicePayment::factory()->for($invoice)->create();
    $this->actingAs($user)->post(route('receipts.email', $payment->id))->assertSessionHasErrors('email');
    Mail::assertNothingSent();
});

test('receipt email delivery errors are shown to the user', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create();
    $payment = InvoicePayment::factory()->for($invoice)->create();
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new TransportException('Testing outage'));
    $this->actingAs($user)->post(route('receipts.email', $payment->id))->assertSessionHasErrors('email');
});

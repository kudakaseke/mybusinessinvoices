<?php

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use App\Models\User;

test('outstanding balances show customers and accurate balances per currency', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['invoice_number' => 'INV-OPEN', 'customer_name' => 'Alice Client', 'customer_email' => 'alice@example.com', 'customer_phone' => '0771234567', 'currency' => 'USD', 'total' => '100.00', 'status' => 'partial', 'due_date' => today('Africa/Johannesburg')->subDay()]);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '30.00']);
    CreditNote::factory()->for($invoice)->create(['amount' => '10.00']);
    InvoiceRefund::factory()->for($invoice)->create(['amount' => '5.00']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'INV-OTHER-CURRENCY', 'customer_name' => 'Alice Client', 'customer_email' => 'ALICE@example.com', 'currency' => 'ZWG', 'total' => '50.00', 'status' => 'outstanding', 'due_date' => today('Africa/Johannesburg')->addDay()]);
    $paid = Invoice::factory()->for($user)->create(['invoice_number' => 'INV-PAID', 'customer_name' => 'Paid Customer', 'total' => '100.00', 'status' => 'paid']);
    InvoicePayment::factory()->for($paid)->create(['amount' => '100.00']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'INV-LEGACY', 'status' => 'paid', 'total' => '100.00']);
    Invoice::factory()->create(['customer_name' => 'Private Customer', 'invoice_number' => 'INV-PRIVATE']);

    $this->actingAs($user)->get('/outstanding-balances')->assertOk()->assertSee('Alice Client')->assertSee('alice@example.com')->assertSee('0771234567')
        ->assertSee('INV-OPEN')->assertSee('USD 65.00')->assertSee('ZWG 50.00')->assertSee('Overdue')->assertSee('Not yet due')
        ->assertSee(route('documents.show', ['invoices', $invoice->id]))->assertDontSee('Paid Customer')->assertDontSee('INV-LEGACY')->assertDontSee('Private Customer');
    $this->get('/dashboard')->assertSee(route('outstanding-balances'));
});

test('outstanding balances require authentication and show an empty state', function () {
    $this->get('/outstanding-balances')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/outstanding-balances')->assertOk()->assertSee('No outstanding balances yet.');
});

test('balances combine a customer invoices and reflect reversed payments and credits', function () {
    $user = User::factory()->create();
    $first = Invoice::factory()->for($user)->create(['customer_name' => 'Grouped Client', 'customer_email' => 'group@example.com', 'currency' => 'USD', 'total' => '100.00', 'status' => 'partial', 'due_date' => today('Africa/Johannesburg')]);
    $payment = InvoicePayment::factory()->for($first)->create(['amount' => '25.00']);
    $payment->delete();
    Invoice::factory()->for($user)->create(['customer_name' => 'Grouped Client', 'customer_email' => 'GROUP@example.com', 'currency' => 'USD', 'total' => '20.00', 'status' => 'outstanding']);
    $credited = Invoice::factory()->for($user)->create(['customer_name' => 'Settled Client', 'total' => '50.00', 'status' => 'credited']);
    CreditNote::factory()->for($credited)->create(['amount' => '50.00']);
    $this->actingAs($user)->get('/outstanding-balances')->assertOk()->assertSee('USD 120.00')->assertSee('Due today')->assertDontSee('Settled Client')
        ->assertViewHas('customers', fn ($customers): bool => $customers->count() === 1 && $customers->first()['invoices']->count() === 2);
});

test('customer groups and their invoices are ordered by latest issue date', function () {
    $user = User::factory()->create();
    Invoice::factory()->for($user)->create(['invoice_number' => 'OLDER-FIRST', 'customer_name' => 'First Client', 'customer_email' => 'first@example.com', 'issue_date' => '2026-01-01', 'status' => 'outstanding']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'OLDER-SECOND', 'customer_name' => 'Second Client', 'customer_email' => 'second@example.com', 'issue_date' => '2026-02-01', 'status' => 'outstanding']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'LATEST-FIRST', 'customer_name' => 'First Client', 'customer_email' => 'first@example.com', 'issue_date' => '2026-03-01', 'status' => 'outstanding']);
    $this->actingAs($user)->get('/outstanding-balances')->assertOk()
        ->assertViewHas('customers', fn ($customers): bool => $customers->first()['name'] === 'First Client'
            && $customers->first()['invoices']->pluck('invoice.invoice_number')->all() === ['LATEST-FIRST', 'OLDER-FIRST']
            && $customers->last()['name'] === 'Second Client');
});

test('outstanding customers can be selected to record a payment against the correct invoice', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_name' => 'Client To Pay', 'invoice_number' => 'INV-TO-PAY', 'total' => '100.00', 'status' => 'outstanding']);
    $paid = Invoice::factory()->for($user)->create(['customer_name' => 'Paid Client', 'total' => '100.00', 'status' => 'paid']);
    $other = Invoice::factory()->create();
    $this->actingAs($user)->get('/outstanding-balances')->assertOk()->assertSee('Record Payment')->assertSee('Client To Pay')->assertSee('INV-TO-PAY')->assertDontSee('Paid Client');
    $this->get(route('outstanding-balances.record-payment', ['invoice_id' => $invoice->id]))
        ->assertRedirect(route('documents.show', ['invoices', $invoice->id, 'record_payment' => 1]));
    $this->get(route('documents.show', ['invoices', $invoice->id, 'record_payment' => 1]))->assertOk()->assertSee('Record a payment');
    $this->get(route('outstanding-balances.record-payment', ['invoice_id' => $other->id]))->assertNotFound();
    $this->get(route('outstanding-balances.record-payment', ['invoice_id' => $paid->id]))->assertRedirect(route('outstanding-balances'))->assertSessionHasErrors('invoice_id');
    $this->from('/outstanding-balances')->get(route('outstanding-balances.record-payment'))->assertSessionHasErrors('invoice_id');
});

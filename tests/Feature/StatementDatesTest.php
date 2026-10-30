<?php

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;

test('daily statements group invoices payments and credits with a closing balance', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '100.00', 'status' => 'partial']);
    Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '50.00', 'status' => 'outstanding']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-02', 'amount' => '25.00']);
    CreditNote::factory()->for($invoice)->create(['credited_on' => '2026-10-02', 'amount' => '10.00']);

    $response = $this->actingAs($user)->get('/statements')->assertOk()->assertSee('Transaction details')->assertDontSee('Daily summary');

    $daily = $response->viewData('daily');
    expect($daily)->toHaveCount(2);
    expect($daily[0])->toMatchArray(['invoice_count' => 2, 'charged' => 15000, 'received' => 0, 'balance' => 15000]);
    expect($daily[1])->toMatchArray(['invoice_count' => 0, 'charged' => 0, 'received' => 2500, 'credited' => 1000, 'balance' => 11500]);
});

test('date filters preserve the opening balance and include payments on older invoices', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-09-01', 'total' => '100.00', 'status' => 'partial', 'invoice_number' => 'OLDER-INVOICE']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-10', 'amount' => '25.00']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-20', 'amount' => '10.00']);
    Invoice::factory()->for($user)->create(['issue_date' => '2026-11-01', 'invoice_number' => 'FUTURE-INVOICE']);

    $response = $this->actingAs($user)->get('/statements?from=2026-10-10&to=2026-10-10')->assertOk()
        ->assertViewHas('openingBalance', 10000)->assertViewHas('charged', 0)->assertViewHas('received', 2500)->assertViewHas('balance', 7500);

    expect($response->viewData('daily'))->toHaveCount(1);
    expect($response->viewData('daily')[0])->toMatchArray(['date_label' => '10 Oct 2026', 'invoice_count' => 0, 'received' => 2500, 'balance' => 7500]);
    $pdf = $this->get('/statements/download?from=2026-10-10&to=2026-10-10')->assertOk()->assertDownload()->getContent();
    expect($pdf)->toContain('DAILY SUMMARY')->toContain('OPENING BALANCE: USD 100.00')->toContain('STILL TO PAY: USD 75.00')->not->toContain('FUTURE-INVOICE');
});

test('undated payments have a separate daily row without being assigned to the invoice date', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-09-01', 'total' => '100.00', 'status' => 'partial']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => null, 'amount' => '25.00']);

    $response = $this->actingAs($user)->get('/statements?from=2026-10-01&to=2026-10-31')->assertOk()
        ->assertViewHas('openingBalance', 10000)->assertViewHas('balance', 7500)->assertDontSee('not assigned to a day');

    expect($response->viewData('daily')[0])->toMatchArray(['date_label' => 'Original date unknown', 'invoice_count' => 0, 'received' => 2500]);
});

test('statement date ranges must be valid and ordered', function (string $query, string $field) {
    $this->actingAs(User::factory()->create())->get('/statements?'.$query)->assertSessionHasErrors($field);
})->with([['from=invalid', 'from'], ['to=invalid', 'to'], ['from=2026-10-20&to=2026-10-01', 'to']]);

test('payment choices use only outstanding invoices in the selected account and currency', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'currency' => 'USD', 'total' => '100.00', 'status' => 'outstanding']);
    Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'currency' => 'USD', 'status' => 'paid']);
    Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'currency' => 'ZWG']);
    Invoice::factory()->create(['customer_email' => 'client@example.com']);

    $response = $this->actingAs($user)->get('/statements?client=client@example.com&currency=USD')->assertOk()->assertSee('Record a payment');

    expect($response->viewData('paymentInvoices')->pluck('id')->all())->toBe([$invoice->id]);
    $this->get('/invoices/'.$invoice->id.'?record_payment=1')->assertOk()->assertSee('id="record-payment"', false);
});

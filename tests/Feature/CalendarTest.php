<?php

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Quotation;
use App\Models\User;

test('calendar groups documents payments and expenses on their recorded dates', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['invoice_number' => 'CAL-INV', 'issue_date' => '2026-10-05', 'total' => '100.00']);
    $quotation = Quotation::factory()->for($user)->create(['quotation_number' => 'CAL-QUO', 'issue_date' => '2026-10-06']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-07', 'amount' => '25.00']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-08', 'amount' => '15.00']);
    $expense = Expense::factory()->for($user)->create(['expense_date' => '2026-10-09', 'merchant' => 'Calendar supplier']);

    $response = $this->actingAs($user)->get('/calendar?month=2026-10')->assertOk()
        ->assertSee('CAL-INV')->assertSee('CAL-QUO')->assertSee('Received payment')->assertSee('Calendar supplier')
        ->assertSee(route('documents.show', ['invoices', $invoice->id]))
        ->assertSee(route('documents.show', ['quotations', $quotation->id]))
        ->assertSee(route('records.edit', ['expenses', $expense->id]));

    $events = $response->viewData('events');
    expect($events['2026-10-05']->first()['type'])->toBe('invoices');
    expect($events['2026-10-06']->first()['type'])->toBe('quotations');
    expect($events['2026-10-07']->first()['amount'])->toBe('25.00');
    expect($events['2026-10-08']->first()['amount'])->toBe('15.00');
    expect($events['2026-10-09']->first()['type'])->toBe('expenses');
});

test('calendar excludes other owners records unknown payment dates and activity outside the visible grid', function () {
    $user = User::factory()->create();
    $private = Invoice::factory()->create(['invoice_number' => 'PRIVATE-INVOICE', 'issue_date' => '2026-10-01']);
    InvoicePayment::factory()->for($private)->create(['paid_on' => '2026-10-02']);
    Quotation::factory()->create(['quotation_number' => 'PRIVATE-QUOTE', 'issue_date' => '2026-10-03']);
    Expense::factory()->create(['merchant' => 'Private supplier', 'expense_date' => '2026-10-04']);
    $old = Invoice::factory()->for($user)->create(['invoice_number' => 'OLD-INVOICE', 'issue_date' => '2026-08-01']);
    InvoicePayment::factory()->for($old)->create(['paid_on' => null]);
    InvoicePayment::factory()->for($old)->create(['paid_on' => '2026-10-15', 'amount' => '5.00']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'VISIBLE-EDGE', 'issue_date' => '2026-09-28']);

    $response = $this->actingAs($user)->get('/calendar?month=2026-10')->assertOk()
        ->assertDontSee('PRIVATE-INVOICE')->assertDontSee('PRIVATE-QUOTE')->assertDontSee('Private supplier')
        ->assertSee('VISIBLE-EDGE');

    $events = $response->viewData('events')->flatten(1);
    expect($events)->toHaveCount(2);
    expect($events->where('type', 'invoices')->pluck('number')->all())->toBe(['VISIBLE-EDGE']);
    expect($events->where('type', 'payments')->first()['date'])->toBe('2026-10-15');
    $this->get('/calendar?month=2026-12')->assertOk()->assertViewHas('events', fn ($events) => $events->isEmpty());
});

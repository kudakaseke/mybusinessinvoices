<?php

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;

test('business statements show dated expenses within the selected range and currency without reducing client debt', function () {
    $user = User::factory()->create();
    Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'currency' => 'USD', 'issue_date' => '2026-10-01', 'total' => '100.00', 'status' => 'outstanding']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-10', 'currency' => 'USD', 'amount' => '5.00', 'merchant' => 'Food shop', 'description' => 'Lunch']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-11', 'currency' => 'USD', 'amount' => '7.50', 'description' => 'Transport']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-09-30', 'currency' => 'USD', 'description' => 'Outside dates']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-10', 'currency' => 'ZWG', 'description' => 'Other currency']);
    Expense::factory()->create(['expense_date' => '2026-10-10', 'description' => 'Private spending']);

    $response = $this->actingAs($user)->get('/statements?currency=USD&from=2026-10-01&to=2026-10-31')->assertOk()
        ->assertSee('Business expenses')->assertSee('Food shop')->assertSee('Lunch')->assertSee('Transport')
        ->assertDontSee('Outside dates')->assertDontSee('Other currency')->assertDontSee('Private spending')
        ->assertViewHas('expenseTotal', 1250)->assertViewHas('balance', 10000);

    expect($response->viewData('expenseEntries'))->toHaveCount(2);
    $this->get('/statements?client=client@example.com&currency=USD')->assertOk()->assertDontSee('Food shop')
        ->assertViewHas('expenseTotal', 0)->assertViewHas('balance', 10000);
    $this->get('/statements?currency=ZWG')->assertOk()->assertSee('Other currency')->assertDontSee('Food shop');
});

test('PDF statements include the filtered business expenses', function () {
    $user = User::factory()->create();
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-10', 'currency' => 'USD', 'amount' => '5.25', 'description' => 'Daily lunch']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-11', 'currency' => 'USD', 'description' => 'Outside day']);

    $pdf = $this->actingAs($user)->get('/statements/download?currency=USD&from=2026-10-10&to=2026-10-10')->assertOk()->assertDownload()->getContent();

    expect($pdf)->toContain('BUSINESS EXPENSES')->toContain('DAILY LUNCH')->toContain('10 OCT 2026')->toContain('TOTAL EXPENSES: USD 5.25')->not->toContain('OUTSIDE DAY');
});

test('calendar totals combine each date activity and keep currencies separate', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '100.00', 'currency' => 'USD']);
    Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '15.00', 'currency' => 'USD']);
    $olderInvoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-09-28', 'total' => '900.00', 'currency' => 'USD']);
    Invoice::factory()->for($user)->create(['issue_date' => '2026-10-02', 'total' => '200.00', 'currency' => 'ZWG']);
    Invoice::factory()->create(['issue_date' => '2026-10-01', 'total' => '999.00']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-10', 'amount' => '25.00']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => null, 'amount' => '10.00']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-10', 'amount' => '5.00', 'currency' => 'USD']);
    CreditNote::factory()->for($invoice)->create(['credited_on' => '2026-10-10', 'amount' => '20.00']);
    CreditNote::factory()->for($olderInvoice)->create(['credited_on' => '2026-09-29', 'amount' => '12.00']);
    CreditNote::factory()->create(['credited_on' => '2026-10-10', 'amount' => '999.00']);

    $response = $this->actingAs($user)->get('/calendar?month=2026-10')->assertOk()->assertSee('Total Invoice:')->assertSee('Total Payments Received:')->assertSee('Total Expenses:')->assertSee('Total Credit Notes:');

    $totals = $response->viewData('dailyTotals');
    expect($totals['2026-10-01']->firstWhere('currency', 'USD'))->toMatchArray(['invoiced' => 11500, 'received' => 0, 'expenses' => 0, 'credited' => 0]);
    expect($totals['2026-10-10']->firstWhere('currency', 'USD'))->toMatchArray(['invoiced' => 0, 'received' => 2500, 'expenses' => 500, 'credited' => 2000]);
    expect($totals['2026-10-02']->firstWhere('currency', 'ZWG'))->toMatchArray(['invoiced' => 20000, 'received' => 0, 'expenses' => 0, 'credited' => 0]);
    expect($totals['2026-09-29']->firstWhere('currency', 'USD')['credited'])->toBe(1200);
    $empty = $this->get('/calendar?month=2026-12')->assertOk()->viewData('dailyTotals');
    expect($empty)->toBeEmpty();
});

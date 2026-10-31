<?php

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;

test('analytics charts weekly invoice amounts and actual dated payments separately', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-19', 'currency' => 'USD', 'total' => '100.00', 'status' => 'partial']);
    Invoice::factory()->for($user)->create(['issue_date' => '2026-10-20', 'currency' => 'USD', 'total' => '50.00']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-21', 'amount' => '20.00']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => '2026-10-30', 'amount' => '10.00']);
    InvoicePayment::factory()->for($invoice)->create(['paid_on' => null, 'amount' => '5.00']);
    CreditNote::factory()->for($invoice)->create(['credited_on' => '2026-10-30', 'amount' => '15.00']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-19', 'currency' => 'USD', 'amount' => '10.15']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-20', 'currency' => 'USD', 'amount' => '5.25']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-30', 'currency' => 'USD', 'amount' => '2.10']);

    $response = $this->actingAs($user)->get('/analytics')->assertOk()->assertSee('Invoices, payments & expenses')
        ->assertSee('Total invoices')->assertSee('Total payments received')->assertSee('Total expenses in range')->assertSee('not included in this date-based graph');

    $activity = $response->viewData('activity');
    expect($activity['weeks'])->toHaveCount(12);
    expect($activity['invoiced'])->toBe(15000);
    expect($activity['received'])->toBe(3000);
    expect($activity['expenses'])->toBe(1750);
    expect($activity['weeks'][10])->toMatchArray(['date' => '2026-10-19', 'invoiced' => 15000, 'received' => 2000]);
    expect($activity['weeks'][11])->toMatchArray(['date' => '2026-10-26', 'invoiced' => 0, 'received' => 1000]);
    expect($activity['weeks'][0]['invoiced'])->toBe(0);
    expect($activity['weeks'][10]['expenses'])->toBe(1540);
    expect($activity['weeks'][11]['expenses'])->toBe(210);
});

test('analytics filters by owner currency and range while including payments on older invoices', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $user = User::factory()->create();
    $older = Invoice::factory()->for($user)->create(['issue_date' => '2026-07-01', 'currency' => 'USD', 'total' => '100.00']);
    InvoicePayment::factory()->for($older)->create(['paid_on' => '2026-10-30', 'amount' => '25.00']);
    InvoicePayment::factory()->for($older)->create(['paid_on' => '2026-07-02', 'amount' => '15.00']);
    InvoicePayment::factory()->for($older)->create(['paid_on' => '2026-10-31', 'amount' => '10.00']);
    $zig = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-30', 'currency' => 'ZWG', 'total' => '200.00']);
    InvoicePayment::factory()->for($zig)->create(['paid_on' => '2026-10-30', 'amount' => '50.00']);
    $private = Invoice::factory()->create(['issue_date' => '2026-10-30', 'currency' => 'USD', 'total' => '999.00']);
    InvoicePayment::factory()->for($private)->create(['paid_on' => '2026-10-30', 'amount' => '999.00']);
    Invoice::factory()->for($user)->create(['issue_date' => '2026-10-31', 'currency' => 'USD', 'total' => '100.00']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-30', 'currency' => 'USD', 'amount' => '4.00']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-30', 'currency' => 'ZWG', 'amount' => '8.00']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-07-01', 'currency' => 'USD', 'amount' => '10.00']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-10-31', 'currency' => 'USD', 'amount' => '900.00']);
    Expense::factory()->create(['expense_date' => '2026-10-30', 'currency' => 'USD', 'amount' => '999.00']);

    $activity = $this->actingAs($user)->get('/analytics?currency=USD')->assertOk()->viewData('activity');

    expect($activity['invoiced'])->toBe(0);
    expect($activity['received'])->toBe(2500);
    expect($activity['expenses'])->toBe(400);
    $zigActivity = $this->get('/analytics?currency=ZWG')->assertOk()->viewData('activity');
    expect($zigActivity['invoiced'])->toBe(20000);
    expect($zigActivity['received'])->toBe(5000);
    expect($zigActivity['expenses'])->toBe(800);
});

test('analytics handles zero activity and validates currencies', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $this->actingAs(User::factory()->create())->get('/analytics?currency=ZWG')->assertOk()
        ->assertSee('No invoice, payment or expense activity')->assertViewHas('activity', fn (array $activity): bool => $activity['invoiced'] === 0 && $activity['received'] === 0 && $activity['expenses'] === 0 && count($activity['weeks']) === 12);
    $this->get('/analytics?currency=EUR')->assertSessionHasErrors('currency');
});

test('expense-only activity is included from the first day of the chart range', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $user = User::factory()->create();
    Expense::factory()->for($user)->create(['expense_date' => '2026-08-10', 'currency' => 'USD', 'amount' => '10.00']);
    Expense::factory()->for($user)->create(['expense_date' => '2026-08-09', 'currency' => 'USD', 'amount' => '100.00']);

    $response = $this->actingAs($user)->get('/analytics')->assertOk()->assertSee('Total expenses')->assertDontSee('No invoice, payment or expense activity');

    expect($response->viewData('activity')['expenses'])->toBe(1000);
    expect($response->viewData('activity')['weeks'][0]['expenses'])->toBe(1000);
});

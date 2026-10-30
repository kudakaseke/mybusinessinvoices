<?php

use App\Models\CreditNote;
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

    $response = $this->actingAs($user)->get('/analytics')->assertOk()->assertSee('Invoice & payment activity')
        ->assertSee('Total invoices')->assertSee('Total payments received')->assertSee('not included in this date-based graph');

    $activity = $response->viewData('activity');
    expect($activity['weeks'])->toHaveCount(12);
    expect($activity['invoiced'])->toBe(15000);
    expect($activity['received'])->toBe(3000);
    expect($activity['weeks'][10])->toMatchArray(['date' => '2026-10-19', 'invoiced' => 15000, 'received' => 2000]);
    expect($activity['weeks'][11])->toMatchArray(['date' => '2026-10-26', 'invoiced' => 0, 'received' => 1000]);
    expect($activity['weeks'][0]['invoiced'])->toBe(0);
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

    $activity = $this->actingAs($user)->get('/analytics?currency=USD')->assertOk()->viewData('activity');

    expect($activity['invoiced'])->toBe(0);
    expect($activity['received'])->toBe(2500);
    $zigActivity = $this->get('/analytics?currency=ZWG')->assertOk()->viewData('activity');
    expect($zigActivity['invoiced'])->toBe(20000);
    expect($zigActivity['received'])->toBe(5000);
});

test('analytics handles zero activity and validates currencies', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $this->actingAs(User::factory()->create())->get('/analytics?currency=ZWG')->assertOk()
        ->assertSee('No invoice or payment activity')->assertViewHas('activity', fn (array $activity): bool => $activity['invoiced'] === 0 && $activity['received'] === 0 && count($activity['weeks']) === 12);
    $this->get('/analytics?currency=EUR')->assertSessionHasErrors('currency');
});

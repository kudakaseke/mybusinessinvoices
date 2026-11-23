<?php

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;

test('dashboard shows both currency overviews when there is no activity', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
        ->assertSee('USD OVERVIEW')->assertSee('ZWG OVERVIEW')
        ->assertViewHas('dashboardTotals', fn ($totals): bool => $totals->count() === 2 && $totals->every(fn ($total): bool => (float) $total->billed === 0.0));
});

test('dashboard keeps invoice payments and expenses in their own currency', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['currency' => 'USD', 'total' => '100.00', 'status' => 'partial']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00']);
    Expense::factory()->for($user)->create(['currency' => 'ZWG', 'amount' => '45.00']);
    Invoice::factory()->create(['currency' => 'ZWG', 'total' => '9999.00']);
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('USD OVERVIEW')->assertSee('ZWG OVERVIEW')->assertSee('45.00')->assertDontSee('9,999.00')
        ->assertViewHas('dashboardTotals', function ($totals): bool {
            $usd = $totals->firstWhere('currency', 'USD');
            $zwg = $totals->firstWhere('currency', 'ZWG');

            return (float) $usd->billed === 100.0 && (float) $usd->paid === 25.0 && (float) $usd->outstanding === 75.0 && (float) $zwg->billed === 0.0;
        })->assertViewHas('expenses', fn ($expenses): bool => (float) $expenses['ZWG'] === 45.0);
});

test('dashboard shows totals for invoices in both currencies', function () {
    $user = User::factory()->create();
    Invoice::factory()->for($user)->create(['currency' => 'USD', 'total' => '100.00', 'status' => 'outstanding']);
    Invoice::factory()->for($user)->create(['currency' => 'ZWG', 'total' => '250.00', 'status' => 'outstanding']);
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('100.00')->assertSee('250.00')
        ->assertViewHas('dashboardTotals', fn ($totals): bool => (float) $totals->firstWhere('currency', 'USD')->billed === 100.0 && (float) $totals->firstWhere('currency', 'ZWG')->billed === 250.0);
});

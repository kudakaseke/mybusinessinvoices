<?php

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

function paymentPayload(string $amount = '25.00'): array
{
    return ['status' => 'paid', 'amount' => $amount, 'paid_on' => '2026-10-20', 'reference' => 'Bank transfer', 'submission_key' => (string) Str::uuid()];
}

test('partial payments remain on record and a later payment settles the invoice', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['total' => '100.00', 'status' => 'outstanding', 'issue_date' => '2026-10-01']);

    $this->actingAs($user)->patch('/invoices/'.$invoice->id.'/payment', paymentPayload())->assertSessionHasNoErrors()->assertRedirect();

    expect($invoice->fresh()->status)->toBe('partial');
    expect($invoice->fresh()->balanceDue())->toBe('75.00');
    $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'amount' => '25.00', 'reference' => 'Bank transfer']);
    expect($invoice->payments()->firstOrFail()->paid_on->toDateString())->toBe('2026-10-20');
    $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('Payment history')->assertSee('Record a payment')->assertSee('75.00');
    $pdf = $this->get('/invoices/'.$invoice->id.'/download')->assertOk()->getContent();
    expect($pdf)->toContain('(PAYMENTS RECEIVED)')->toContain('($25.00 USD)')->toContain('($75.00 USD)');
    $overview = $this->get('/reports')->assertOk();
    $overview->assertSee('25.00')->assertSee('75.00');

    $this->patch('/invoices/'.$invoice->id.'/payment', paymentPayload('75.00'))->assertSessionHasNoErrors()->assertRedirect();

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->balanceDue())->toBe('0.00');
    $this->assertDatabaseCount('invoice_payments', 2);
    $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('Fully paid. Nothing left to pay.')->assertDontSee('Record a payment');
});

test('invalid payments do not alter the balance or create records', function (string $field, mixed $value) {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['total' => '100.00', 'status' => 'outstanding', 'issue_date' => '2026-10-01']);
    $payload = paymentPayload();
    $payload[$field] = $value;

    $this->actingAs($user)->from('/invoices/'.$invoice->id)->patch('/invoices/'.$invoice->id.'/payment', $payload)
        ->assertRedirect('/invoices/'.$invoice->id)->assertSessionHasErrors($field);

    $this->assertDatabaseCount('invoice_payments', 0);
    expect($invoice->fresh()->balanceDue())->toBe('100.00');
})->with([
    ['amount', '0'], ['amount', '-1'], ['amount', '100.01'], ['amount', '0.001'], ['amount', null],
    ['paid_on', '2026-09-30'], ['paid_on', '2026-10-31'], ['paid_on', null], ['submission_key', 'invalid'],
]);

test('repeated payment submissions only record one payment', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['total' => '100.00', 'status' => 'outstanding', 'issue_date' => '2026-10-01']);
    $payload = paymentPayload();

    $this->actingAs($user)->patch('/invoices/'.$invoice->id.'/payment', $payload)->assertSessionHasNoErrors();
    $this->patch('/invoices/'.$invoice->id.'/payment', $payload)->assertSessionHasNoErrors();
    $this->patch('/invoices/'.$invoice->id.'/payment', ['status' => 'outstanding'])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('invoice_payments', 1);
    expect($invoice->fresh()->balanceDue())->toBe('75.00');
    expect($invoice->fresh()->status)->toBe('partial');
});

test('statements show dated charges payments and running balances for one client and currency', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['invoice_number' => 'VISIBLE-USD', 'customer_name' => 'Example Client', 'customer_email' => 'client@example.com', 'currency' => 'USD', 'total' => '100.00', 'issue_date' => '2026-10-01', 'status' => 'partial']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00', 'paid_on' => '2026-10-20']);
    Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'currency' => 'ZWG', 'invoice_number' => 'HIDDEN-ZWG']);
    Invoice::factory()->for($user)->create(['customer_email' => 'other@example.com', 'invoice_number' => 'HIDDEN-CLIENT']);
    Invoice::factory()->create(['customer_email' => 'client@example.com', 'invoice_number' => 'PRIVATE-OTHER-OWNER']);

    $response = $this->actingAs($user)->get('/statements?client=client@example.com&currency=USD');

    $response->assertOk()->assertSee('Example Client')->assertSee('01 Oct 2026')->assertSee('20 Oct 2026')
        ->assertSee('Invoice charge')->assertSee('Payment received')->assertSee('Still to pay')
        ->assertSee('VISIBLE-USD')->assertDontSee('HIDDEN-ZWG')->assertDontSee('HIDDEN-CLIENT')->assertDontSee('PRIVATE-OTHER-OWNER')
        ->assertViewHas('balance', 7500)->assertViewHas('charged', 10000)->assertViewHas('received', 2500);
    expect($response->viewData('entries')->pluck('balance')->all())->toBe([10000, 7500]);
    $this->get('/statements?client=client@example.com&currency=ZWG')->assertOk()->assertSee('HIDDEN-ZWG')->assertDontSee('VISIBLE-USD');
});

test('statements require authentication and handle an empty account', function () {
    $this->get('/statements')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/statements')->assertOk()->assertSee('No invoice activity');
});

test('statements default to all clients while keeping currencies and owners separate', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_name' => 'First Client', 'customer_email' => 'first@example.com', 'invoice_number' => 'FIRST-USD', 'total' => '100.00', 'status' => 'partial', 'currency' => 'USD']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00']);
    Invoice::factory()->for($user)->create(['customer_name' => 'Second Client', 'customer_email' => 'second@example.com', 'invoice_number' => 'SECOND-USD', 'total' => '50.00', 'status' => 'outstanding', 'currency' => 'USD']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'ONLY-ZIG', 'total' => '200.00', 'currency' => 'ZWG']);
    Invoice::factory()->create(['invoice_number' => 'OTHER-OWNER']);

    $this->actingAs($user)->get('/statements')->assertOk()->assertSee('All clients')
        ->assertSee('FIRST-USD')->assertSee('SECOND-USD')->assertDontSee('ONLY-ZIG')->assertDontSee('OTHER-OWNER')
        ->assertViewHas('client', null)->assertViewHas('charged', 15000)->assertViewHas('received', 2500)->assertViewHas('balance', 12500);
    $this->get('/statements?client=&currency=ZWG')->assertOk()->assertSee('ONLY-ZIG')->assertDontSee('FIRST-USD')->assertDontSee('SECOND-USD')
        ->assertViewHas('currency', 'ZWG')->assertViewHas('charged', 20000);
    $pdf = $this->get('/statements/download?client=&currency=USD')->assertOk()->assertDownload()->getContent();
    expect($pdf)->toContain('ALL CLIENTS')->toContain('FIRST CLIENT')->toContain('SECOND CLIENT')->toContain('STILL TO PAY: USD 125.00')->not->toContain('ONLY-ZIG')->not->toContain('OTHER-OWNER');
});

test('switching to a currency without invoices clears activity and shows zero balances', function () {
    $user = User::factory()->create();
    Invoice::factory()->for($user)->create(['invoice_number' => 'USD-ONLY', 'customer_email' => 'client@example.com', 'currency' => 'USD']);

    $this->actingAs($user)->get('/statements?client=client@example.com&currency=ZWG')
        ->assertOk()->assertSee('No invoice activity')->assertSee('ZWG 0.00')->assertDontSee('USD-ONLY')
        ->assertViewHas('currency', 'ZWG')->assertViewHas('balance', 0)->assertViewHas('charged', 0)->assertViewHas('received', 0);
    $this->get('/statements?client=&currency=ZWG')->assertOk()->assertSee('No invoice activity')->assertViewHas('balance', 0);
});

test('client reports download a PDF with only the selected account and currency', function () {
    $user = User::factory()->create(['business_name' => 'My studio']);
    $invoice = Invoice::factory()->for($user)->create(['invoice_number' => 'REPORT-USD', 'customer_name' => 'Report Client', 'customer_email' => 'client@example.com', 'total' => '100.00', 'status' => 'partial', 'currency' => 'USD']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00', 'paid_on' => '2026-10-20']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'OTHER-CURRENCY', 'customer_email' => 'client@example.com', 'currency' => 'ZWG']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'OTHER-CLIENT', 'customer_email' => 'other@example.com']);
    Invoice::factory()->create(['invoice_number' => 'PRIVATE-INVOICE', 'customer_email' => 'client@example.com']);

    $response = $this->actingAs($user)->get('/statements/download?client=client@example.com&currency=USD')
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload('Client-statement-USD.pdf');

    expect($response->getContent())->toStartWith('%PDF-1.4')->toContain('%%EOF')
        ->toContain('MY STUDIO')->toContain('REPORT CLIENT')->toContain('REPORT-USD')
        ->toContain('STILL TO PAY: USD 75.00')->toContain('PAYMENTS RECEIVED: USD 25.00')
        ->not->toContain('OTHER-CURRENCY')->not->toContain('OTHER-CLIENT')->not->toContain('PRIVATE-INVOICE');
    $this->get('/statements?client=client@example.com&currency=USD')->assertOk()->assertSee('Generate report (PDF)')->assertSee(route('statements.download'));
});

test('report downloads require authentication and handle empty or invalid selections', function () {
    $this->get('/statements/download')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/statements/download')->assertOk()->assertDownload();
    $this->get('/statements/download?currency=EUR')->assertSessionHasErrors('currency');
});

test('long client reports include all payments across multiple PDF pages', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'total' => '100.00', 'status' => 'partial']);
    InvoicePayment::factory()->count(40)->for($invoice)->sequence(fn ($sequence) => ['reference' => 'Transfer '.($sequence->index + 1)])->create(['amount' => '1.00', 'paid_on' => '2026-10-20']);

    $response = $this->actingAs($user)->get('/statements/download?client=client@example.com&currency=USD')->assertOk()->assertDownload();

    $pdf = $response->getContent();
    expect($pdf)->toContain('TRANSFER 1')->toContain('TRANSFER 40')->toContain('STILL TO PAY: USD 60.00');
    preg_match('/\/Type \/Pages \/Count (\d+)/', $pdf, $matches);
    expect((int) $matches[1])->toBeGreaterThan(1);
});

test('existing paid invoices keep a clearly labelled payment record without inventing a payment date', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['status' => 'paid', 'total' => '100.00', 'customer_email' => 'client@example.com']);
    Schema::drop('invoice_payments');
    $migration = require database_path('migrations/2026_10_30_174450_create_invoice_payments_table.php');

    $migration->up();

    $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'amount' => '100.00', 'paid_on' => null]);
    $this->actingAs($user)->get('/statements?client=client@example.com&currency=USD')
        ->assertOk()->assertSee('Original date unknown')->assertViewHas('balance', 0)->assertViewHas('received', 10000);
});

test('payment amounts use exact cents and reject payment above the remaining balance', function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['total' => '0.30', 'status' => 'outstanding', 'issue_date' => '2026-10-01']);

    $this->actingAs($user)->patch('/invoices/'.$invoice->id.'/payment', paymentPayload('0.10'))->assertSessionHasNoErrors();
    $this->patch('/invoices/'.$invoice->id.'/payment', paymentPayload('0.21'))->assertSessionHasErrors('amount');
    $this->patch('/invoices/'.$invoice->id.'/payment', paymentPayload('0.20'))->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('paid');
    expect($invoice->fresh()->balanceDue())->toBe('0.00');
    $this->assertDatabaseCount('invoice_payments', 2);
});

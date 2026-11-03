<?php

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 30));
});

function creditPayload(string $amount = '30.00'): array
{
    return ['amount' => $amount, 'credited_on' => '2026-10-20', 'reason' => 'Returned goods', 'submission_key' => (string) Str::uuid()];
}

test('credit notes reduce invoice balances and appear separately from cash in statements reports and calendar', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->hasItems(1)->create(['issue_date' => '2026-10-01', 'total' => '100.00', 'status' => 'outstanding', 'customer_email' => 'client@example.com']);

    $this->actingAs($user)->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload())
        ->assertSessionHasNoErrors()->assertRedirect(route('documents.show', ['invoices', $invoice->id]));

    $note = CreditNote::firstOrFail();
    expect($invoice->fresh()->balanceDue())->toBe('70.00');
    expect($invoice->fresh()->paidAmount())->toBe('0.00');
    expect($invoice->fresh()->total)->toBe('100.00');
    expect($invoice->items()->count())->toBe(1);
    $this->assertDatabaseHas('credit_notes', ['invoice_id' => $invoice->id, 'amount' => '30.00', 'reason' => 'Returned goods']);
    $this->assertDatabaseCount('invoice_payments', 0);
    $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee($note->number)->assertSee('70.00')->assertSee('Issue a credit note');
    $this->get('/credit-notes')->assertOk()->assertSee($note->number)->assertSee('Returned goods');
    $statement = $this->get('/statements?client=client@example.com&currency=USD')->assertOk()
        ->assertViewHas('balance', 7000)->assertViewHas('received', 0)->assertViewHas('credited', 3000)->assertSee($note->number);
    expect($statement->viewData('entries')->last()['credited'])->toBe(3000);
    $report = $this->get('/reports')->assertOk()->viewData('totals')->first();
    expect((float) $report->outstanding)->toBe(70.0);
    expect((float) $report->paid)->toBe(0.0);
    expect((float) $report->credited)->toBe(30.0);
    $calendar = $this->get('/calendar?month=2026-10')->assertOk()->assertSee('Total Credit Notes:');
    expect($calendar->viewData('events')['2026-10-20']->first()['type'])->toBe('credit-notes');
    $pdf = $this->get('/credit-notes/'.$note->id.'/download')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload($note->number.'.pdf')->getContent();
    expect($pdf)->toStartWith('%PDF-1.4')->toContain($note->number)->toContain('RETURNED GOODS')->toContain('USD 30.00');
    $statementPdf = $this->get('/statements/download?client=client@example.com&currency=USD')->assertOk()->getContent();
    expect($statementPdf)->toContain('CREDIT NOTES: USD 30.00')->toContain('PAYMENTS RECEIVED: USD 0.00')->toContain('STILL TO PAY: USD 70.00');
});

test('a later payment settles a partially credited invoice without inflating cash received', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '100.00', 'status' => 'partial']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '25.00', 'paid_on' => '2026-10-10']);

    $this->actingAs($user)->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload('40.00'))->assertSessionHasNoErrors();
    expect($invoice->fresh()->balanceDue())->toBe('35.00');
    $this->patch('/invoices/'.$invoice->id.'/payment', ['status' => 'paid', 'amount' => '35.00', 'paid_on' => '2026-10-25', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors();

    expect($invoice->fresh()->status)->toBe('settled');
    expect($invoice->fresh()->paidAmount())->toBe('60.00');
    expect($invoice->fresh()->creditedAmount())->toBe('40.00');
    expect($invoice->fresh()->balanceDue())->toBe('0.00');
});

test('crediting a paid invoice keeps cash history and exposes client credit without creating a refund', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '100.00', 'status' => 'paid', 'customer_email' => 'client@example.com']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '100.00', 'paid_on' => '2026-10-10']);

    $this->actingAs($user)->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload('20.00'))->assertSessionHasNoErrors();

    expect($invoice->fresh()->customerCredit())->toBe('20.00');
    expect($invoice->fresh()->paidAmount())->toBe('100.00');
    expect($invoice->fresh()->balanceDue())->toBe('0.00');
    $this->assertDatabaseCount('invoice_payments', 1);
    $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('Client credit:')->assertSee('No cash refund has been recorded.');
    $this->get('/statements?client=client@example.com')->assertOk()->assertViewHas('balance', -2000)->assertViewHas('received', 10000)->assertSee('Balance (client credit)');
    $totals = $this->get('/reports')->assertOk()->viewData('totals')->first();
    expect((float) $totals->outstanding)->toBe(0.0);
    expect((float) $totals->client_credit)->toBe(20.0);
});

test('duplicate credit submissions are idempotent and cumulative credits cannot exceed the invoice total', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '100.00', 'status' => 'outstanding']);
    $payload = creditPayload('60.00');

    $this->actingAs($user)->post('/invoices/'.$invoice->id.'/credit-notes', $payload)->assertSessionHasNoErrors();
    $this->post('/invoices/'.$invoice->id.'/credit-notes', $payload)->assertSessionHasNoErrors();
    $this->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload('40.01'))->assertSessionHasErrors('amount');
    $this->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload('40.00'))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('credit_notes', 2);
    expect($invoice->fresh()->status)->toBe('credited');
    expect($invoice->fresh()->creditedAmount())->toBe('100.00');
    expect($invoice->fresh()->paidAmount())->toBe('0.00');
    expect($invoice->fresh()->balanceDue())->toBe('0.00');
});

test('invalid credit notes do not change the invoice', function (string $field, mixed $value) {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'total' => '100.00', 'status' => 'outstanding']);
    $payload = creditPayload();
    $payload[$field] = $value;

    $this->actingAs($user)->from('/invoices/'.$invoice->id)->post('/invoices/'.$invoice->id.'/credit-notes', $payload)->assertSessionHasErrors($field);

    $this->assertDatabaseCount('credit_notes', 0);
    expect($invoice->fresh()->balanceDue())->toBe('100.00');
})->with([['amount', 0], ['amount', -1], ['amount', '100.01'], ['amount', '1.001'], ['reason', ''], ['credited_on', '2026-09-30'], ['credited_on', '2026-10-31'], ['submission_key', 'invalid']]);

test('credit notes are restricted to the invoice owner', function () {
    $owner = User::factory()->create();
    $invoice = Invoice::factory()->for($owner)->create(['issue_date' => '2026-10-01']);
    $note = CreditNote::factory()->for($invoice)->create();

    $this->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload())->assertRedirect(route('login'));
    $this->get('/credit-notes')->assertRedirect(route('login'));
    $this->get('/credit-notes/'.$note->id.'/download')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload())->assertNotFound();
    $this->get('/credit-notes/'.$note->id.'/download')->assertNotFound();
    $this->get('/credit-notes')->assertOk()->assertDontSee($note->number);

    $this->assertDatabaseCount('credit_notes', 1);
});

test('credit notes list offers a creation page with only the owners available invoices', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['invoice_number' => 'OWN-AVAILABLE', 'issue_date' => '2026-10-01']);
    $fullyCredited = Invoice::factory()->for($user)->create(['invoice_number' => 'FULLY-CREDITED', 'total' => '100.00']);
    CreditNote::factory()->for($fullyCredited)->create(['amount' => '100.00']);
    $private = Invoice::factory()->create(['invoice_number' => 'PRIVATE-INVOICE']);

    $this->actingAs($user)->get('/credit-notes')->assertOk()->assertSee('Create credit note')->assertSee(route('credit-notes.create'));
    $this->get('/credit-notes/create')->assertOk()->assertSee('OWN-AVAILABLE')->assertDontSee('FULLY-CREDITED')->assertDontSee('PRIVATE-INVOICE');
    $this->get('/credit-notes/create?invoice='.$invoice->id)->assertOk()->assertSee('Credit amount (including tax)')->assertSee(route('credit-notes.store', $invoice->id));
    $this->get('/credit-notes/create?invoice='.$private->id)->assertNotFound();
});

test('credit note creation requires authentication and handles an empty invoice list', function () {
    $this->get('/credit-notes/create')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/credit-notes/create')->assertOk()->assertSee('No invoices are available to credit.');
});

test('credit note searching matches numbers and clients within the owners records', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_name' => 'Find this client']);
    $note = CreditNote::factory()->for($invoice)->create(['number' => 'CN-SEARCH-ONE']);
    $other = CreditNote::factory()->for(Invoice::factory()->for($user))->create(['number' => 'CN-NO-MATCH']);
    $private = CreditNote::factory()->create(['number' => 'CN-SEARCH-PRIVATE']);

    $this->actingAs($user)->get('/credit-notes?search=SEARCH')->assertOk()->assertSee($note->number)->assertDontSee($other->number)->assertDontSee($private->number);
    $this->get('/credit-notes?search=Find')->assertOk()->assertSee($note->number)->assertDontSee($other->number);
});

test('crediting an older paid invoice preserves its known paid balance with an unknown payment date', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-10-01', 'status' => 'paid', 'total' => '100.00']);

    $this->actingAs($user)->post('/invoices/'.$invoice->id.'/credit-notes', creditPayload('10.00'))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('invoice_payments', ['invoice_id' => $invoice->id, 'amount' => '100.00', 'paid_on' => null]);
    expect($invoice->fresh()->paidAmount())->toBe('100.00');
    expect($invoice->fresh()->customerCredit())->toBe('10.00');
});

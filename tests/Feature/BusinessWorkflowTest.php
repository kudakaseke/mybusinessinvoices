<?php

use App\DocumentTotals;
use App\Mail\BusinessDocumentMail;
use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\CreditNote;
use App\Models\DocumentDraft;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;

function workflowPayload(): array
{
    return [
        'invoice_number' => 'INV-WORKFLOW-001', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-15',
        'business_name' => 'Test Business', 'business_email' => 'owner@example.com',
        'customer_name' => 'Test Customer', 'customer_email' => 'customer@example.com',
        'prepared_by' => 'Test Owner', 'currency' => 'USD', 'vat_rate' => '0', 'discount_rate' => '0',
        'status' => 'outstanding', 'items' => [['description' => 'Service', 'quantity' => '1', 'unit_price' => '100']],
    ];
}

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 31));
});

test('drafts save incomplete documents restore all lines and disappear only after final save', function () {
    $user = User::factory()->create();
    $key = (string) Str::uuid();
    $this->actingAs($user)->postJson('/invoices/draft', ['key' => $key, 'payload' => ['customer_email' => 'unfinished@']])
        ->assertOk()->assertJsonPath('key', $key);
    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('clients', 0);
    $payload = workflowPayload();
    $payload['items'][] = ['description' => 'Second draft line', 'quantity' => '2', 'unit_price' => '5'];
    $this->postJson('/invoices/draft', ['key' => $key, 'payload' => $payload])->assertOk();
    $this->assertDatabaseCount('document_drafts', 1);
    $this->get('/invoices/create?draft='.$key)->assertOk()->assertSee('Second draft line')->assertSee('Test Owner');
    $this->get('/drafts')->assertOk()->assertSee('Test Customer');
    $this->post('/invoices/review', $payload + ['draft_key' => $key])->assertOk();
    $this->assertDatabaseCount('document_drafts', 1);
    $this->post('/invoices', $payload + ['draft_key' => $key])->assertSessionHasNoErrors()->assertRedirect();
    $this->assertDatabaseCount('document_drafts', 0);
    expect(Invoice::firstOrFail()->items)->toHaveCount(2);
});

test('drafts reject foreign access and untrusted fields', function () {
    $draft = DocumentDraft::factory()->create();
    $user = User::factory()->create();
    $this->actingAs($user)->get('/invoices/create?draft='.$draft->key)->assertNotFound();
    $this->delete('/drafts/'.$draft->key)->assertNotFound();
    $this->postJson('/invoices/draft', ['key' => $draft->key, 'payload' => ['customer_name' => 'Intruder']])->assertNotFound();
    $key = (string) Str::uuid();
    $this->postJson('/invoices/draft', ['key' => $key, 'payload' => ['user_id' => $draft->user_id, 'total' => '0', 'customer_name' => 'Own draft']])->assertOk();
    expect(DocumentDraft::where('key', $key)->firstOrFail()->payload)->toBe(['customer_name' => 'Own draft']);
    $this->postJson('/invoices/draft', ['key' => $key, 'payload' => ['issue_date' => 'not-a-date']])->assertUnprocessable();
    $this->get('/drafts')->assertDontSee($draft->payload['customer_name']);
});

test('quotation conversion preserves items prevents duplicate invoices and retains the quotation', function () {
    $user = User::factory()->create();
    $quote = Quotation::factory()->for($user)->hasItems(2)->create(['prepared_by' => 'Quotation Owner']);
    $this->actingAs($user)->post('/quotations/'.$quote->id.'/convert')->assertRedirect();
    $draft = DocumentDraft::firstOrFail();
    $this->post('/quotations/'.$quote->id.'/convert')->assertRedirect();
    $this->assertDatabaseCount('document_drafts', 1);
    $this->get('/invoices/create?draft='.$draft->key)->assertOk()->assertSee('Quotation Owner');
    $payload = $draft->payload + ['invoice_number' => 'INV-CONVERTED-001', 'draft_key' => $draft->key];
    $this->post('/invoices', $payload)->assertSessionHasNoErrors()->assertRedirect();
    $invoice = Invoice::firstOrFail();
    expect($invoice->quotation_id)->toBe($quote->id);
    expect($invoice->items)->toHaveCount(2);
    $this->assertDatabaseCount('quotations', 1);
    $this->assertDatabaseCount('document_drafts', 0);
    $this->post('/quotations/'.$quote->id.'/convert')->assertRedirect(route('documents.show', ['invoices', $invoice->id]));
    $this->postJson('/invoices', array_replace($payload, ['invoice_number' => 'INV-SECOND-001']))->assertUnprocessable();
    $this->assertDatabaseCount('invoices', 1);
});

test('payment corrections retain history reverse balances and keep historical statements accurate', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['total' => '100.00', 'status' => 'partial', 'issue_date' => '2026-10-01', 'due_date' => '2026-10-15']);
    $payment = InvoicePayment::factory()->for($invoice)->create(['amount' => '25.15', 'paid_on' => '2026-10-10']);
    $this->actingAs($user)->get(route('payments.receipt', [$invoice->id, $payment->id]))->assertOk()->assertDownload('Receipt-'.$payment->id.'.pdf');
    $correction = ['reason' => 'Entered twice', 'voided_on' => '2026-10-30'];
    $this->post(route('payments.void', [$invoice->id, $payment->id]), $correction)->assertSessionHasNoErrors();
    $this->post(route('payments.void', [$invoice->id, $payment->id]), $correction)->assertSessionHasNoErrors();
    expect($payment->fresh()->trashed())->toBeTrue();
    expect($invoice->fresh()->balanceDue())->toBe('100.00');
    expect($invoice->fresh()->status)->toBe('outstanding');
    $this->get(route('payments.receipt', [$invoice->id, $payment->id]))->assertNotFound();
    $this->get('/invoices/'.$invoice->id)->assertOk()->assertSee('Entered twice')->assertSee('Reversed on');
    $this->get('/statements?to=2026-10-20')->assertOk()->assertViewHas('balance', 7485);
    $this->get('/statements')->assertOk()->assertViewHas('balance', 10000)->assertViewHas('received', 0);
    $this->get('/analytics')->assertOk()->assertViewHas('activity', fn ($activity) => $activity['received'] === 0);
    $this->get('/calendar?month=2026-10')->assertOk()->assertViewHas('dailyTotals', fn ($days) => $days['2026-10-30'][0]['received'] === -2515);
    $this->get('/reports')->assertOk()->assertViewHas('totals', fn ($totals) => (float) $totals->first()->outstanding === 100.0);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'record_type' => 'InvoicePayment', 'action' => 'voided']);
});

test('refunds require available credit are idempotent and reconcile every reporting surface', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['total' => '100.00', 'status' => 'paid', 'issue_date' => '2026-10-01']);
    InvoicePayment::factory()->for($invoice)->create(['amount' => '100.00', 'paid_on' => '2026-10-10']);
    CreditNote::factory()->for($invoice)->create(['amount' => '50.00', 'credited_on' => '2026-10-20']);
    $this->actingAs($user);
    $data = ['amount' => '20.25', 'refunded_on' => '2026-10-25', 'reason' => 'Returned goods', 'submission_key' => (string) Str::uuid()];
    $this->post('/invoices/'.$invoice->id.'/refunds', $data)->assertSessionHasNoErrors();
    $this->post('/invoices/'.$invoice->id.'/refunds', $data)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('invoice_refunds', 1);
    expect($invoice->fresh()->refundedAmount())->toBe('20.25');
    expect($invoice->fresh()->customerCredit())->toBe('29.75');
    expect($invoice->fresh()->balanceDue())->toBe('0.00');
    $this->postJson('/invoices/'.$invoice->id.'/refunds', array_replace($data, ['amount' => '30', 'submission_key' => (string) Str::uuid()]))->assertUnprocessable();
    $this->get('/statements')->assertOk()->assertViewHas('balance', -2975)->assertSee('Cash refund');
    $this->get('/statements/excel')->assertOk()->assertDownload();
    $this->get('/statements/download')->assertOk()->assertDownload();
    $this->get('/analytics')->assertOk()->assertViewHas('activity', fn ($activity) => $activity['received'] === 7975);
    $this->get('/calendar?month=2026-10')->assertOk()->assertViewHas('dailyTotals', fn ($days) => $days['2026-10-25'][0]['received'] === -2025);
    $this->get('/reports')->assertOk()->assertViewHas('totals', fn ($totals) => round((float) $totals->first()->client_credit, 2) === 29.75);
});

test('document emails send only to the owned client with an attached PDF and record success', function () {
    Mail::fake();
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->hasItems()->create();
    $this->actingAs($user)->post('/invoices/'.$invoice->id.'/email', ['email' => 'attacker@example.com'])->assertSessionHasNoErrors();
    Mail::assertSent(BusinessDocumentMail::class, fn ($mail) => $mail->hasTo($invoice->customer_email) && ! $mail->hasTo('attacker@example.com') && str_starts_with($mail->pdf, '%PDF-') && count($mail->attachments()) === 1);
    $this->assertDatabaseHas('activity_logs', ['user_id' => $user->id, 'action' => 'emailed', 'record_id' => $invoice->id]);
});

test('SMTP failures are shown without falsely recording delivery', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->hasItems()->create();
    Mail::shouldReceive('to')->once()->with($invoice->customer_email)->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new TransportException('Testing outage'));
    $this->actingAs($user)->post('/invoices/'.$invoice->id.'/email')->assertSessionHasErrors('email');
    $this->assertDatabaseMissing('activity_logs', ['action' => 'emailed']);
});

test('overdue reminders are opt in deduplicated and skip settled invoices', function () {
    Mail::fake();
    $user = User::factory()->create(['payment_reminders_enabled' => true]);
    $overdue = Invoice::factory()->for($user)->hasItems()->create(['issue_date' => '2026-10-01', 'due_date' => '2026-10-15']);
    Invoice::factory()->for($user)->create(['due_date' => '2026-10-15', 'status' => 'paid']);
    Invoice::factory()->create(['due_date' => '2026-10-15']);
    $this->artisan('invoices:remind')->assertSuccessful();
    Mail::assertSentCount(1);
    expect($overdue->fresh()->last_reminded_at)->not->toBeNull();
    $this->artisan('invoices:remind')->assertSuccessful();
    Mail::assertSentCount(1);
    $this->actingAs($user)->post('/invoices/'.$overdue->id.'/remind')->assertSessionHasErrors('email');
    $this->get('/invoices?status=overdue')->assertOk()->assertViewHas('records', fn ($records) => $records->count() === 1)->assertSee('Overdue');
});

test('activity history is scoped and excludes secrets', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $client = Client::factory()->for($owner)->create(['name' => 'Original Client']);
    $client->update(['name' => 'Updated Client']);
    ActivityLog::factory()->create(['user_id' => $other->id, 'description' => 'OTHER-PRIVATE-EVENT']);
    $this->actingAs($owner)->get('/activity')->assertOk()->assertSee('Updated Client')->assertDontSee('OTHER-PRIVATE-EVENT');
    $this->get('/activity?from=invalid')->assertRedirect();
    expect(ActivityLog::where('user_id', $owner->id)->pluck('changes')->toJson())->not->toContain('password', 'remember_token');
});

test('new financial actions and private drafts enforce ownership', function () {
    $invoice = Invoice::factory()->hasItems()->create();
    $payment = InvoicePayment::factory()->for($invoice)->create();
    $quote = Quotation::factory()->create();
    $this->actingAs(User::factory()->create());
    $this->post('/quotations/'.$quote->id.'/convert')->assertNotFound();
    $this->post('/invoices/'.$invoice->id.'/email')->assertNotFound();
    $this->post('/invoices/'.$invoice->id.'/remind')->assertNotFound();
    $this->post('/invoices/'.$invoice->id.'/refunds')->assertNotFound();
    $this->post(route('payments.void', [$invoice->id, $payment->id]))->assertNotFound();
    $this->get(route('payments.receipt', [$invoice->id, $payment->id]))->assertNotFound();
});

test('private workflow routes require verified authentication', function () {
    foreach (['/drafts', '/activity', '/invoices/1/payments/1/receipt'] as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
    $this->actingAs(User::factory()->unverified()->create())->post('/invoices/draft', [])->assertRedirect(route('verification.notice'));
});

test('signed monetary calculations handle refunds without losing cents', function () {
    expect(DocumentTotals::scaled('-20.25'))->toBe(-2025);
    expect(DocumentTotals::scaled('-0.25'))->toBe(-25);
    expect(DocumentTotals::decimal(-2025))->toBe('-20.25');
    expect(DocumentTotals::decimal(-25))->toBe('-0.25');
});

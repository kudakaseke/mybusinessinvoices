<?php

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('saved documents show success and sharing choices with separate preparer details', function (string $kind) {
    $user = User::factory()->create();
    $data = documentPayload($kind) + ['prepared_by' => 'Alex Smith'];

    $this->actingAs($user)->post('/'.$kind, $data)->assertRedirect()->assertSessionHas('message', 'Document saved.');
    $document = ($kind === 'invoices' ? Invoice::class : Quotation::class)::firstOrFail();
    $this->assertDatabaseHas($kind, ['id' => $document->id, 'prepared_by' => 'Alex Smith', 'payment_account_number' => null]);
    $this->get('/'.$kind.'/'.$document->id)->assertOk()
        ->assertSee('saved successfully!')
        ->assertSee('Download')
        ->assertSee('mailto:', false)
        ->assertSee('https://wa.me/', false)
        ->assertSee('Alex Smith');
})->with(['invoices', 'quotations']);

test('calendar shows the selected month and navigation', function () {
    $this->actingAs(User::factory()->create())->get('/calendar?month=2026-12')
        ->assertOk()->assertSee('December 2026')
        ->assertSee('month=2026-11', false)->assertSee('month=2027-01', false)
        ->assertSee('2026-12-31');
});

test('calendar requires authentication', function () {
    $this->get('/calendar')->assertRedirect(route('login'));
});

test('saving an invoice asks whether payment was received', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/invoices', documentPayload())->assertRedirect();
    $invoice = Invoice::firstOrFail();
    $this->get('/invoices/'.$invoice->id)->assertOk()
        ->assertSee('Have you received payment for this invoice?')
        ->assertSee('Payment received')->assertSee('Not yet paid');
});

test('owners can record invoice payment without changing invoice contents', function (string $status) {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->hasItems(1)->create(['status' => 'outstanding', 'issue_date' => today()->subDay()]);

    $response = $this->actingAs($user)->patch('/invoices/'.$invoice->id.'/payment', ['status' => $status, 'amount' => $status === 'paid' ? $invoice->total : null, 'paid_on' => today('Africa/Johannesburg')->toDateString(), 'submission_key' => (string) Str::uuid(), 'total' => '1.00', 'customer_name' => 'Changed']);

    $response->assertRedirect(route('documents.show', ['invoices', $invoice->id]))
        ->assertSessionHas('message', 'Payment status updated.');
    expect($invoice->fresh()->status)->toBe($status);
    expect($invoice->fresh()->total)->toBe($invoice->total);
    expect($invoice->fresh()->customer_name)->toBe($invoice->customer_name);
    expect($invoice->items()->count())->toBe(1);
    $this->get($response->headers->get('Location'))->assertOk()->assertSee('Download')
        ->assertDontSee('Have you received payment for this invoice?');
})->with(['paid', 'outstanding']);

test('invoice payment rejects invalid statuses', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['status' => 'outstanding']);

    $this->actingAs($user)->from('/invoices/'.$invoice->id)
        ->patch('/invoices/'.$invoice->id.'/payment', ['status' => 'partial'])
        ->assertSessionHasErrors('status');

    expect($invoice->fresh()->status)->toBe('outstanding');
});

test('invoice payment is restricted to its authenticated owner', function () {
    $invoice = Invoice::factory()->create(['status' => 'outstanding']);

    $this->patch('/invoices/'.$invoice->id.'/payment', ['status' => 'paid'])->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->patch('/invoices/'.$invoice->id.'/payment', ['status' => 'paid'])->assertNotFound();

    expect($invoice->fresh()->status)->toBe('outstanding');
});

test('saving documents automatically saves new clients for their owner', function (string $kind) {
    $user = User::factory()->create();
    $payload = documentPayload($kind) + ['customer_phone' => '+263123456789', 'customer_address' => '10 Main Street'];

    $this->actingAs($user)->post('/'.$kind, $payload)->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('clients', ['user_id' => $user->id, 'name' => 'Client', 'email' => 'client@example.com', 'phone' => '+263123456789', 'address' => '10 Main Street']);
    $this->get('/clients')->assertOk()->assertSee('client@example.com');
})->with(['invoices', 'quotations']);

test('saving another document reuses the client without overwriting their contact details', function () {
    $user = User::factory()->create();
    $client = Client::factory()->for($user)->create(['email' => 'CLIENT@example.com', 'name' => 'Saved client', 'phone' => '123']);

    $this->actingAs($user)->post('/invoices', documentPayload())->assertSessionHasNoErrors();
    $this->post('/quotations', documentPayload('quotations'))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('clients', 1);
    expect($client->fresh()->name)->toBe('Saved client');
    expect($client->fresh()->phone)->toBe('123');
});

test('automatic client saving never reuses another owners client', function () {
    $otherClient = Client::factory()->create(['email' => 'client@example.com', 'name' => 'Private client']);
    $user = User::factory()->create();

    $this->actingAs($user)->post('/invoices', documentPayload())->assertSessionHasNoErrors();

    $this->assertDatabaseCount('clients', 2);
    $this->assertDatabaseHas('clients', ['user_id' => $user->id, 'email' => 'client@example.com', 'name' => 'Client']);
    expect($otherClient->fresh()->name)->toBe('Private client');
});

test('reviewing or rejecting a document does not create a client', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/invoices/review', documentPayload())->assertOk();
    $this->post('/invoices', array_replace(documentPayload(), ['customer_email' => 'invalid']))->assertSessionHasErrors('customer_email');

    $this->assertDatabaseCount('clients', 0);
});

test('existing document clients are recovered without duplicating or replacing saved clients', function () {
    $user = User::factory()->create();
    Client::factory()->for($user)->create(['email' => 'client@example.com', 'name' => 'Keep this name']);
    Invoice::factory()->for($user)->create(['customer_email' => 'CLIENT@example.com']);
    Quotation::factory()->for($user)->create(['customer_email' => 'new@example.com', 'customer_name' => 'Recovered client']);
    $migration = require database_path('migrations/2026_10_30_175405_backfill_clients_from_documents.php');

    $migration->up();
    $migration->up();

    $this->assertDatabaseCount('clients', 2);
    $this->assertDatabaseHas('clients', ['user_id' => $user->id, 'email' => 'new@example.com', 'name' => 'Recovered client']);
    $this->assertDatabaseHas('clients', ['user_id' => $user->id, 'email' => 'client@example.com', 'name' => 'Keep this name']);
});

function documentPayload(string $kind = 'invoices'): array
{
    return [
        $kind === 'invoices' ? 'invoice_number' : 'quotation_number' => 'TEST-0001',
        'issue_date' => '2026-10-01', 'due_date' => '2026-10-31',
        'business_name' => 'Studio', 'business_email' => 'studio@example.com',
        'customer_name' => 'Client', 'customer_email' => 'client@example.com',
        'prepared_by' => 'Alex Smith',
        'currency' => 'USD', 'vat_rate' => '15', 'discount_rate' => '10',
        ...($kind === 'invoices' ? ['status' => 'outstanding'] : ['notes' => 'Valid until the due date.']),
        'items' => [['description' => 'Design', 'quantity' => '2.50', 'unit_price' => '19.99']],
    ];
}

test('review and saving require the preparer name', function (string $path) {
    $user = User::factory()->create();
    $data = documentPayload(str_starts_with($path, '/quotations') ? 'quotations' : 'invoices');
    unset($data['prepared_by']);

    $this->actingAs($user)->post($path, $data)->assertSessionHasErrors('prepared_by');

    $this->assertDatabaseCount('invoices', 0);
    $this->assertDatabaseCount('quotations', 0);
})->with(['/invoices/review', '/quotations/review', '/invoices', '/quotations']);

test('PDF receipt text is uppercase while stored input is preserved', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->hasItems(1)->create(['business_name' => 'My Studio', 'prepared_by' => 'Alex Smith']);

    $response = $this->actingAs($user)->get('/invoices/'.$invoice->id.'/download')->assertOk();

    expect($response->getContent())->toContain('(MY STUDIO)')->toContain('(PREPARED BY: ALEX SMITH)');
    expect($invoice->fresh()->business_name)->toBe('My Studio');
});

test('Bill to details appear in order and preserve the customer email casing in PDF', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create([
        'customer_name' => 'Example Client', 'customer_address' => '10 Main Street',
        'customer_phone' => '+263123456789', 'customer_email' => 'client@example.com',
    ]);

    $response = $this->actingAs($user)->get('/invoices/'.$invoice->id.'/download')->assertOk();

    $pdf = $response->getContent();
    expect($pdf)->toContain('(CUSTOMER NAME:)')->toContain('(EXAMPLE CLIENT)')
        ->toContain('(CUSTOMER PHYSICAL ADDRESS:)')->toContain('(10 MAIN STREET)')
        ->toContain('(PHONE NUMBER:)')->toContain('(+263123456789)')
        ->toContain('(CUSTOMER EMAIL ADDRESS:)')->toContain('(client@example.com)')
        ->not->toContain('CLIENT@EXAMPLE.COM');
    expect(strpos($pdf, 'CUSTOMER NAME:'))->toBeLessThan(strpos($pdf, 'CUSTOMER PHYSICAL ADDRESS:'));
    expect(strpos($pdf, 'CUSTOMER PHYSICAL ADDRESS:'))->toBeLessThan(strpos($pdf, 'PHONE NUMBER:'));
    expect(strpos($pdf, 'PHONE NUMBER:'))->toBeLessThan(strpos($pdf, 'CUSTOMER EMAIL ADDRESS:'));
});

test('financial screens require authentication', function (string $path) {
    $this->get($path)->assertRedirect(route('login'));
})->with(['/dashboard', '/analytics', '/reports', '/invoices', '/quotations', '/clients', '/products', '/expenses', '/settings']);

test('all reconstructed screens render for an authenticated user', function (string $path) {
    $user = User::factory()->create();

    $this->actingAs($user)->get($path)->assertOk();
})->with(['/dashboard', '/analytics', '/reports', '/invoices', '/quotations', '/clients', '/products', '/expenses', '/settings', '/invoices/create', '/quotations/create', '/clients/create', '/products/create', '/expenses/create']);

test('documents calculate amounts and ignore supplied totals and owner', function (string $kind, string $itemTable) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $data = documentPayload($kind) + ['user_id' => $other->id, 'total' => '0.01'];

    $this->actingAs($user)->post('/'.$kind, $data)->assertRedirect();

    $this->assertDatabaseHas($kind, ['user_id' => $user->id, 'subtotal' => '49.98', 'discount_amount' => '5.00', 'vat_amount' => '6.75', 'total' => '51.73']);
    $this->assertDatabaseHas($itemTable, ['quantity' => '2.50', 'unit_price' => '19.99', 'amount' => '49.98']);
    $this->assertDatabaseMissing($kind, ['user_id' => $other->id]);
})->with([['invoices', 'invoice_items'], ['quotations', 'quotation_items']]);

test('documents reject products owned by another user', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();
    $data = documentPayload();
    $data['items'][0]['product_id'] = $product->id;

    $this->actingAs($user)->post('/invoices', $data)->assertSessionHasErrors('items.0.product_id');

    $this->assertDatabaseCount('invoices', 0);
});

test('documents reject invalid amounts and dates without saving', function (string $field, mixed $value) {
    $user = User::factory()->create();
    $data = documentPayload();
    data_set($data, $field, $value);

    $this->actingAs($user)->post('/invoices', $data)->assertSessionHasErrors($field);

    $this->assertDatabaseCount('invoices', 0);
})->with([['items.0.quantity', 0], ['items.0.unit_price', -1], ['vat_rate', 101], ['discount_rate', 101], ['due_date', '2026-09-30'], ['currency', 'EUR'], ['items.0.quantity', '1.001'], ['customer_email', 'invalid']]);

test('document limits fail with validation rather than overflowing', function () {
    $user = User::factory()->create();
    $data = documentPayload();
    $data['items'][0]['quantity'] = '1000000';
    $data['items'][0]['unit_price'] = '1000000000';

    $this->actingAs($user)->post('/invoices', $data)->assertSessionHasErrors('items');

    $this->assertDatabaseCount('invoices', 0);
});

test('documents are inaccessible to another user for reading and editing', function (string $kind) {
    $user = User::factory()->create();
    $document = ($kind === 'invoices' ? Invoice::class : Quotation::class)::factory()->create();

    $this->actingAs($user)->get('/'.$kind.'/'.$document->id)->assertNotFound();
    $this->get('/'.$kind.'/'.$document->id.'/edit')->assertNotFound();
    $this->put('/'.$kind.'/'.$document->id, documentPayload($kind))->assertNotFound();

    expect($document->fresh()->total)->toBe('100.00');
})->with(['invoices', 'quotations']);

test('saved invoices reject editing and retain their original items and totals', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->hasItems(2)->create();
    $data = documentPayload();
    $data['items'][0]['unit_price'] = '10.00';

    $this->actingAs($user)->get('/invoices/'.$invoice->id.'/edit')->assertForbidden();
    $this->put('/invoices/'.$invoice->id, $data)->assertForbidden();

    expect($invoice->fresh()->total)->toBe('100.00');
    expect($invoice->items()->count())->toBe(2);
});

test('review renders a separate PDF page without saving the document', function (string $kind) {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/'.$kind.'/review', documentPayload($kind))
        ->assertOk()->assertViewIs('documents.review')
        ->assertSee('data:application/pdf;base64,', false)->assertSee('Back to edit')->assertSee('Save');

    $this->assertDatabaseCount($kind, 0);
})->with(['invoices', 'quotations']);

test('saving a duplicate document from review returns to the form with errors and input', function (string $kind) {
    $user = User::factory()->create();
    $data = documentPayload($kind);
    $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
    ($kind === 'invoices' ? Invoice::class : Quotation::class)::factory()->for($user)->create([$number => $data[$number]]);

    $response = $this->actingAs($user)->from('/'.$kind.'/review')->post('/'.$kind, $data);

    $response->assertRedirect(route('documents.create', $kind))
        ->assertSessionHasErrors($number)
        ->assertSessionHasInput('customer_name', $data['customer_name'])
        ->assertSessionHasInput('items.0.description', 'Design');
    $this->assertDatabaseCount($kind, 1);
    $this->get($response->headers->get('Location'))->assertOk()
        ->assertSee('has already been taken.')->assertSee($data[$number]);
})->with(['invoices', 'quotations']);

test('download returns an actual PDF and is restricted to the document owner', function (string $kind) {
    $user = User::factory()->create();
    $document = ($kind === 'invoices' ? Invoice::class : Quotation::class)::factory()->for($user)->hasItems(2)->create();

    $response = $this->actingAs($user)->get('/'.$kind.'/'.$document->id.'/download')
        ->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertDownload();
    expect($response->getContent())->toStartWith('%PDF-1.4')->toContain('%%EOF');
    $this->actingAs(User::factory()->create())->get('/'.$kind.'/'.$document->id.'/download')->assertNotFound();
})->with(['invoices', 'quotations']);

test('saved quotations cannot be modified', function () {
    $user = User::factory()->create();
    $quotation = Quotation::factory()->for($user)->create();

    $this->actingAs($user)->get('/quotations/'.$quotation->id.'/edit')->assertForbidden();
    $this->put('/quotations/'.$quotation->id, documentPayload('quotations'))->assertForbidden();

    expect($quotation->fresh()->total)->toBe('100.00');
});

test('invoice output escapes client supplied text', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->create(['customer_name' => '<script>alert(1)</script>']);

    $this->actingAs($user)->get('/invoices/'.$invoice->id)->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});

test('dashboard excludes another users documents and separates currencies', function () {
    $user = User::factory()->create();
    Invoice::factory()->for($user)->create(['invoice_number' => 'OWN-USD', 'total' => '125.00']);
    Invoice::factory()->for($user)->create(['invoice_number' => 'OWN-ZWG', 'currency' => 'ZWG', 'total' => '2000.00']);
    Invoice::factory()->create(['invoice_number' => 'PRIVATE-INVOICE']);

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('OWN-USD')->assertSee('OWN-ZWG')->assertDontSee('PRIVATE-INVOICE')->assertSee('125.00')->assertSee('2,000.00');
});

test('business records can be created without accepting another owner', function (string $kind, array $data) {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->post('/'.$kind, $data + ['user_id' => $other->id])->assertRedirect(route('records.index', $kind));

    $expected = $data;
    unset($expected['expense_date']);
    $this->assertDatabaseHas($kind, $expected + ['user_id' => $user->id]);
    if ($kind === 'expenses') {
        expect(Expense::firstOrFail()->expense_date->toDateString())->toBe('2026-10-01');
    }
    $this->assertDatabaseMissing($kind, ['user_id' => $other->id]);
})->with([
    ['clients', ['name' => 'Client', 'email' => 'client@example.com']],
    ['products', ['name' => 'Service', 'unit_price' => '12.50', 'currency' => 'USD']],
    ['expenses', ['merchant' => 'Shop', 'description' => 'Paper', 'category' => 'Office', 'expense_date' => '2026-10-01', 'currency' => 'USD', 'amount' => '12.50']],
]);

test('business record edits are scoped to their owner', function (string $kind, string $model) {
    $user = User::factory()->create();
    $entry = $model::factory()->create();

    $this->actingAs($user)->get('/'.$kind.'/'.$entry->id.'/edit')->assertNotFound();
    $this->put('/'.$kind.'/'.$entry->id, [])->assertNotFound();
})->with([['clients', Client::class], ['products', Product::class], ['expenses', Expense::class]]);

test('expense receipts are stored privately and restricted to their owner', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)->post('/expenses', [
        'merchant' => 'Shop', 'description' => 'Paper', 'category' => 'Office', 'expense_date' => '2026-10-01', 'currency' => 'USD', 'amount' => '12.50',
        'receipt' => UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
    ])->assertRedirect(route('records.index', 'expenses'));

    $expense = Expense::firstOrFail();
    Storage::disk('local')->assertExists($expense->receipt_path);
    $this->get('/expenses/'.$expense->id.'/receipt')->assertOk()->assertDownload('receipt.pdf');
    $this->actingAs($other)->get('/expenses/'.$expense->id.'/receipt')->assertNotFound();
});

test('missing recovered receipt returns not found', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $expense = Expense::factory()->for($user)->create(['receipt_path' => 'receipts/missing.pdf']);

    $this->actingAs($user)->get('/expenses/'.$expense->id.'/receipt')->assertNotFound();
});

test('business settings persist existing defaults without changing account credentials', function () {
    $user = User::factory()->create();
    $data = ['business_name' => 'Studio', 'base_currency' => 'USD', 'target_currency' => 'ZWG', 'exchange_rate' => '27.5', 'default_tax_rate' => '15', 'default_currencies' => ['USD', 'ZWG'], 'password' => 'changed'];

    $this->actingAs($user)->put('/settings', $data)->assertRedirect();

    expect($user->fresh()->business_name)->toBe('Studio');
    expect($user->fresh()->exchange_rate)->toBe('27.500000');
    expect($user->fresh()->password)->toBe($user->password);
});

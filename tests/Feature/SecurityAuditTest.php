<?php

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\AccountDeletionCode;
use App\Notifications\VerifyEmailCode;
use App\Providers\AppServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Volt\Volt;
use Symfony\Component\Mailer\Exception\TransportException;

function auditProtectedEndpoints(): array
{
    $endpoints = [];
    foreach (['dashboard', 'analytics', 'reports', 'calendar', 'settings', 'settings/profile', 'settings/password', 'settings/appearance', 'statements', 'statements/download', 'statements/excel', 'credit-notes', 'credit-notes/create', 'credit-notes/1/download', 'expenses/1/receipt'] as $path) {
        $endpoints[] = ['GET', '/'.$path];
    }
    foreach (['invoices', 'quotations'] as $kind) {
        foreach (['', '/create', '/1', '/1/edit', '/1/download'] as $suffix) {
            $endpoints[] = ['GET', '/'.$kind.$suffix];
        }
        foreach (['', '/review', '/resume'] as $suffix) {
            $endpoints[] = ['POST', '/'.$kind.$suffix];
        }
        $endpoints[] = ['PUT', '/'.$kind.'/1'];
    }
    foreach (['clients', 'products', 'expenses'] as $kind) {
        foreach (['', '/create', '/1/edit'] as $suffix) {
            $endpoints[] = ['GET', '/'.$kind.$suffix];
        }
        $endpoints[] = ['POST', '/'.$kind];
        $endpoints[] = ['PUT', '/'.$kind.'/1'];
    }

    return [...$endpoints, ['PUT', '/settings'], ['PATCH', '/invoices/1/payment'], ['POST', '/invoices/1/credit-notes']];
}

test('audit: every business endpoint rejects guests', function (string $method, string $path) {
    $this->call($method, $path)->assertRedirect(route('login'));
})->with(auditProtectedEndpoints());

test('audit: every business endpoint rejects unverified accounts', function (string $method, string $path) {
    $this->actingAs(User::factory()->unverified()->create())
        ->call($method, $path)->assertRedirect(route('verification.notice'));
})->with(auditProtectedEndpoints());

test('audit: owners can visit every business page and download format', function () {
    Storage::fake('local');
    Storage::disk('local')->put('receipts/audit.pdf', '%PDF-1.4 audit');
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    $invoice = Invoice::factory()->for($user)->hasItems(2)->create(['total' => '100.00']);
    $quote = Quotation::factory()->for($user)->hasItems(2)->create();
    $client = Client::factory()->for($user)->create();
    $product = Product::factory()->for($user)->create();
    $expense = Expense::factory()->for($user)->create(['receipt_path' => 'receipts/audit.pdf', 'receipt_original_name' => 'audit.pdf']);
    $credit = CreditNote::factory()->for($invoice)->create(['amount' => '10.00']);
    $this->actingAs($user);

    foreach (['dashboard', 'analytics', 'reports', 'calendar', 'settings', 'settings/profile', 'settings/password', 'settings/appearance', 'confirm-password', 'statements', 'statements/download', 'statements/excel', 'credit-notes', 'credit-notes/create',
        'credit-notes/create?invoice='.$invoice->id, 'credit-notes/'.$credit->id.'/download', 'expenses/'.$expense->id.'/receipt',
        'invoices', 'invoices/create', 'invoices/'.$invoice->id, 'invoices/'.$invoice->id.'/download',
        'quotations', 'quotations/create', 'quotations/'.$quote->id, 'quotations/'.$quote->id.'/download',
        'clients', 'clients/create', 'clients/'.$client->id.'/edit', 'products', 'products/create', 'products/'.$product->id.'/edit', 'expenses', 'expenses/create', 'expenses/'.$expense->id.'/edit'] as $path) {
        $this->get('/'.$path)->assertOk();
    }
    foreach (['invoices/'.$invoice->id, 'quotations/'.$quote->id] as $path) {
        $this->get('/'.$path.'/edit')->assertForbidden();
        $this->put('/'.$path, [])->assertForbidden();
    }
});

test('audit: other accounts cannot read change pay credit or download owned records', function () {
    $owner = User::factory()->create();
    $invoice = Invoice::factory()->for($owner)->create();
    $quote = Quotation::factory()->for($owner)->create();
    $credit = CreditNote::factory()->for($invoice)->create();
    $client = Client::factory()->for($owner)->create();
    $product = Product::factory()->for($owner)->create();
    $expense = Expense::factory()->for($owner)->create();
    $this->actingAs(User::factory()->create(['business_profile_completed_at' => now()]));

    foreach (['invoices/'.$invoice->id, 'quotations/'.$quote->id] as $path) {
        foreach (['', '/edit', '/download'] as $suffix) {
            $this->get('/'.$path.$suffix)->assertNotFound();
        }
        $this->put('/'.$path)->assertNotFound();
    }
    foreach (['clients' => $client, 'products' => $product, 'expenses' => $expense] as $kind => $record) {
        $this->get('/'.$kind.'/'.$record->id.'/edit')->assertNotFound();
        $this->put('/'.$kind.'/'.$record->id)->assertNotFound();
    }
    $this->patch('/invoices/'.$invoice->id.'/payment')->assertNotFound();
    $this->post('/invoices/'.$invoice->id.'/credit-notes')->assertNotFound();
    $this->get('/credit-notes/'.$credit->id.'/download')->assertNotFound();
    $this->get('/credit-notes/create?invoice='.$invoice->id)->assertNotFound();
    $this->get('/expenses/'.$expense->id.'/receipt')->assertNotFound();
    $this->get('/statements?client='.urlencode($invoice->customer_email))->assertOk()->assertViewHas('entries', fn ($entries) => $entries->isEmpty());
});

test('audit: normal record document payment credit and reporting workflow', function () {
    Storage::fake('local');
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    $this->actingAs($user);
    $client = ['name' => 'Audit Client', 'email' => 'audit-client@example.com'];
    $product = ['name' => 'Audit Product', 'currency' => 'USD', 'unit_price' => '50.00'];
    $expense = ['merchant' => 'Audit Shop', 'description' => 'Lunch', 'category' => 'Food', 'expense_date' => today()->toDateString(), 'currency' => 'USD', 'amount' => '5.00'];
    $this->post('/clients', $client)->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/products', $product)->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/expenses', $expense + ['receipt' => UploadedFile::fake()->create('receipt.pdf', 1, 'application/pdf')])->assertSessionHasNoErrors()->assertRedirect();
    $this->put('/clients/'.Client::firstOrFail()->id, $client + ['phone' => '0771234567'])->assertSessionHasNoErrors()->assertRedirect();
    $this->put('/products/'.Product::firstOrFail()->id, $product + ['description' => 'Service'])->assertSessionHasNoErrors()->assertRedirect();
    $this->put('/expenses/'.Expense::firstOrFail()->id, array_replace($expense, ['description' => 'Updated lunch']))->assertSessionHasNoErrors()->assertRedirect();
    foreach (['invoices' => 'invoice_number', 'quotations' => 'quotation_number'] as $kind => $number) {
        $payload = [$number => 'AUDIT-001', 'issue_date' => today()->toDateString(), 'due_date' => today()->toDateString(),
            'business_name' => 'Audit Business', 'business_email' => $user->email, 'customer_name' => 'Audit Client', 'customer_email' => $client['email'],
            'prepared_by' => 'Audit Tester', 'currency' => 'USD', 'vat_rate' => '0', 'discount_rate' => '0', 'status' => 'outstanding',
            'items' => [['description' => 'Service', 'quantity' => '2', 'unit_price' => '50.00']]];
        $this->post('/'.$kind.'/review', $payload)->assertOk();
        $this->post('/'.$kind.'/resume', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/'.$kind, $payload)->assertSessionHasNoErrors()->assertRedirect();
    }
    $invoice = Invoice::firstOrFail();
    $this->patch('/invoices/'.$invoice->id.'/payment', ['status' => 'paid', 'amount' => '25.00', 'paid_on' => today()->toDateString(), 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/invoices/'.$invoice->id.'/credit-notes', ['amount' => '10.00', 'credited_on' => today()->toDateString(), 'reason' => 'Return', 'submission_key' => (string) Str::uuid()])->assertSessionHasNoErrors()->assertRedirect();
    $this->get('/statements')->assertOk()->assertViewHas('balance', 6500)->assertViewHas('expenseTotal', 500);
    $this->get('/statements/download')->assertOk()->assertDownload();
    $this->get('/statements/excel')->assertOk()->assertDownload();
    $this->put('/settings', ['business_name' => 'Audit Business', 'business_email' => $user->email, 'base_currency' => 'USD', 'target_currency' => 'ZWG', 'exchange_rate' => '27.5', 'default_tax_rate' => '0', 'default_currencies' => ['USD', 'ZWG']])->assertSessionHasNoErrors()->assertRedirect();
    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

test('security: settings cannot bypass required business setup', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put('/settings', ['base_currency' => 'USD', 'target_currency' => 'ZWG', 'exchange_rate' => '27.5', 'default_tax_rate' => '0', 'default_currencies' => ['USD']])->assertSessionHasNoErrors();

    expect($user->fresh()->business_name)->toBeNull();
    expect($user->fresh()->business_profile_completed_at)->toBeNull();
    $this->get('/dashboard')->assertOk()->assertSeeLivewire('business-setup');
});

test('security: account deletion removes receipts and password reset tokens', function () {
    Notification::fake();
    Storage::fake('local');
    $user = User::factory()->create();
    $other = User::factory()->create();
    Storage::disk('local')->put('receipts/'.$other->id.'/private.pdf', 'other receipt');
    Storage::disk('local')->put('receipts/'.$user->id.'/private.pdf', 'private receipt');
    Expense::factory()->for($user)->create(['receipt_path' => 'receipts/'.$user->id.'/private.pdf']);
    Password::createToken($user);
    $this->actingAs($user);

    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    Notification::assertSentTo($user, AccountDeletionCode::class);
    $component->set('code', $code)->call('deleteUser')->assertHasNoErrors();

    expect($user->fresh())->toBeNull();
    Storage::disk('local')->assertMissing('receipts/'.$user->id.'/private.pdf');
    Storage::disk('local')->assertExists('receipts/'.$other->id.'/private.pdf');
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
});

test('security: deleted account reset tokens cannot reset a recreated account', function () {
    Notification::fake();
    Storage::fake('local');
    $user = User::factory()->create();
    $email = $user->email;
    $token = Password::createToken($user);
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    Notification::assertSentTo($user, AccountDeletionCode::class);
    $component->set('code', $code)->call('deleteUser')->assertHasNoErrors();
    $replacement = User::factory()->create(['email' => $email]);

    Volt::test('auth.reset-password', ['token' => $token])->set('email', $email)
        ->set('password', 'replacement-password')->set('password_confirmation', 'replacement-password')
        ->call('resetPassword')->assertHasErrors(['email']);

    expect(Hash::check('password', $replacement->fresh()->password))->toBeTrue();
});

test('security: receipt cleanup failure keeps the account and reports a friendly error', function () {
    Notification::fake();
    $user = User::factory()->create();
    Password::createToken($user);
    Storage::shouldReceive('disk')->with('local')->once()->andReturn(
        Mockery::mock(FilesystemAdapter::class, function ($mock): void {
            $mock->shouldReceive('deleteDirectory')->once()->andReturnFalse();
        })
    );
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    Notification::assertSentTo($user, AccountDeletionCode::class);
    $component->set('code', $code)->call('deleteUser')->assertHasErrors(['password']);
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('password_reset_tokens', ['email' => $user->email]);
});

test('security: password recovery rejects untrusted hosts', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->withServerVariables(['HTTP_HOST' => 'attacker.example', 'SERVER_NAME' => 'attacker.example']);
    $this->get('http://attacker.example/forgot-password')->assertStatus(400);
    Notification::assertNothingSent();
    expect(parse_url((new ResetPassword('test-token'))->toMail($user)->actionUrl, PHP_URL_HOST))
        ->toBe(parse_url(config('app.url'), PHP_URL_HOST));
});

test('security: password resets invalidate established sessions', function () {
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login')->assertHasNoErrors();
    $oldSession = session()->all();
    Auth::logout();
    session()->flush();
    $token = Password::createToken($user);
    Volt::test('auth.reset-password', ['token' => $token])->set('email', $user->email)
        ->set('password', 'reset-password')->set('password_confirmation', 'reset-password')->call('resetPassword')->assertHasNoErrors();

    Auth::forgetGuards();
    $this->withSession($oldSession)->get('/dashboard')->assertRedirect(route('login'));
    expect(Hash::check('reset-password', $user->fresh()->password))->toBeTrue();
});

test('audit: unsigned private storage and Livewire uploads are rejected', function () {
    $this->get('/storage/receipts/private.pdf')->assertForbidden();
    $this->put('/storage/receipts/private.pdf', ['contents' => 'test'])->assertForbidden();
    $this->post(route('livewire.upload-file'))->assertUnauthorized();
    $this->get(route('livewire.preview-file', ['filename' => 'private.pdf']))->assertUnauthorized();
});

test('audit: search injection and stored scripts do not expose another owner or execute in HTML', function () {
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    Client::factory()->create(['name' => 'OTHER-OWNER-PRIVATE']);
    $script = '<script>alert("audit")</script>';
    Client::factory()->for($user)->create(['name' => $script]);
    $this->actingAs($user)->get('/clients')->assertOk()->assertSee($script)->assertDontSee($script, false);
    $this->get('/clients?search='.urlencode("%' OR 1=1 --"))->assertOk()->assertDontSee('OTHER-OWNER-PRIVATE');
});

test('security: malformed search parameters return validation errors', function (string $kind) {
    $this->actingAs(User::factory()->create(['business_profile_completed_at' => now()]))
        ->getJson('/'.$kind.'?search[]=invalid')->assertUnprocessable();
})->with(['invoices', 'quotations', 'clients', 'products', 'expenses', 'credit-notes']);

test('security: oversized numeric record identifiers return not found', function () {
    $this->actingAs(User::factory()->create())
        ->get('/invoices/999999999999999999999999999999')->assertNotFound();
});

test('audit: expense-only reports still display the expense amount', function () {
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    Expense::factory()->for($user)->create(['currency' => 'USD', 'amount' => '5.00']);

    $response = $this->actingAs($user)->get('/reports')->assertOk()->assertSee('5.00');

    expect((float) $response->viewData('expenses')['USD'])->toBe(5.0);
    expect($response->viewData('totals'))->toBeEmpty();
});

test('security: SMTP failure allows confirmation recovery without caching an undelivered code', function () {
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Audit simulated unavailable SMTP'));

    Volt::test('auth.register')->set('name', 'Audit SMTP User')->set('email', 'audit-smtp@example.com')
        ->set('password', 'password')->set('password_confirmation', 'password')->call('register')
        ->assertHasNoErrors()->assertRedirect(route('verification.notice', absolute: false));

    $user = User::where('email', 'audit-smtp@example.com')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect(Cache::has('email-verification:'.$user->id))->toBeFalse();
    expect(session('verification.user_id'))->toBe($user->id);
    $this->travel(61)->seconds();
    Notification::fake();
    Volt::test('auth.verify-email')->call('sendVerification')->assertHasNoErrors();
    Notification::assertSentTo($user, VerifyEmailCode::class);
    expect(Cache::has('email-verification:'.$user->id))->toBeTrue();
});

test('security: statement client emails are matched case insensitively', function () {
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    Invoice::factory()->for($user)->create(['customer_email' => 'Client@Example.com', 'total' => '100.00']);
    Invoice::factory()->for($user)->create(['customer_email' => 'client@example.com', 'total' => '200.00']);

    $response = $this->actingAs($user)->get('/statements?client=client@example.com')->assertOk()->assertViewHas('balance', 30000);

    expect($response->viewData('clients'))->toHaveCount(1);
    $this->get('/statements')->assertOk()->assertViewHas('balance', 30000);
});

test('security: verification page does not log out verified users', function () {
    $user = User::factory()->create(['business_profile_completed_at' => now()]);

    $this->actingAs($user)->get('/verify-email')->assertRedirect('/dashboard');

    $this->assertAuthenticatedAs($user);
});

test('audit: the actual login action rejects a wrong password for an existing account', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')->set('email', $user->email)->set('password', 'wrong-audit-password')
        ->call('login')->assertHasErrors(['email']);

    $this->assertGuest();
});

test('security: public pages send browser security headers', function () {
    $this->get('/login')->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');
});

test('security: production disables debug output and enables HTTPS transport protection', function () {
    app()->instance('env', 'production');
    config(['app.debug' => true]);
    (new AppServiceProvider(app()))->register();
    expect(config('app.debug'))->toBeFalse();
    $this->get('https://localhost/login')->assertOk()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});

test('security: browser log ingestion is disabled', function () {
    $this->postJson('/_boost/browser-logs', ['logs' => []])->assertNotFound();
});

test('security: unavailable optional vendor assets return not found', function (string $path) {
    $this->get($path)->assertNotFound();
})->with(['/flux/editor.css', '/flux/editor.js', '/flux/editor.min.js', '/flux/phone.js', '/flux/phone.min.js', '/flux/phone-utils.js']);

test('security: component modules without sources return not found', function (string $module) {
    $path = parse_url(route('default-livewire.update'), PHP_URL_PATH);
    $prefix = substr($path, 0, strrpos($path, '/'));
    $this->get($prefix.'/'.$module)->assertNotFound();
})->with(['css/auth--login.css', 'css/auth--login.global.css', 'js/auth--login.js']);

test('security: revoking database sessions only removes the intended account sessions', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    $other = User::factory()->create();
    foreach (['current' => $user->id, 'old' => $user->id, 'other' => $other->id] as $identifier => $owner) {
        DB::table('sessions')->insert([
            'id' => $identifier, 'user_id' => $owner, 'payload' => base64_encode(serialize([])), 'last_activity' => now()->timestamp,
        ]);
    }
    $user->revokeSessions('current');
    $this->assertDatabaseHas('sessions', ['id' => 'current']);
    $this->assertDatabaseMissing('sessions', ['id' => 'old']);
    $this->assertDatabaseHas('sessions', ['id' => 'other']);
    $user->revokeSessions();
    $this->assertDatabaseMissing('sessions', ['id' => 'current']);
    $this->assertDatabaseHas('sessions', ['id' => 'other']);
});

test('security: changing a password preserves the current session and rejects its old copy', function () {
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login');
    $oldSession = session()->all();
    Volt::test('settings.password')->set('current_password', 'password')
        ->set('password', 'new-password')->set('password_confirmation', 'new-password')
        ->call('updatePassword')->assertHasNoErrors();
    $this->get('/dashboard')->assertOk();
    Auth::forgetGuards();
    $this->withSession($oldSession)->get('/dashboard')->assertRedirect(route('login'));
});

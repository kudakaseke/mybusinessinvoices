<?php

use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Symfony\Component\Mailer\Exception\TransportException;

test('business settings cannot skip personal onboarding even with complete business details', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put('/settings', [
        'business_name' => 'My Studio', 'business_email' => $user->email,
        'business_phone' => '0771234567', 'business_address' => 'Harare',
        'base_currency' => 'USD', 'target_currency' => 'ZWG', 'exchange_rate' => '26',
        'default_tax_rate' => '0', 'default_currencies' => ['USD'],
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->business_profile_completed_at)->toBeNull();
    $this->get('/dashboard')->assertOk()->assertSeeLivewire('business-setup');
});

test('password recovery reports mail failure and allows retry after a minute', function () {
    $user = User::factory()->create();
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Simulated unavailable SMTP'));
    $component = Volt::test('auth.forgot-password')->set('email', $user->email)
        ->call('sendPasswordResetLink')->assertHasErrors(['email']);
    expect(session('status'))->toBeNull();

    $this->travel(61)->seconds();
    Notification::fake();
    $component->call('sendPasswordResetLink')->assertHasNoErrors();
    Notification::assertSentTo($user, ResetPassword::class);
});

test('repeated recovery requests are rate limited for both known and unknown emails', function (bool $registered) {
    Notification::fake();
    $email = $registered ? User::factory()->create()->email : 'unknown@example.com';
    $component = Volt::test('auth.forgot-password')->set('email', $email)
        ->call('sendPasswordResetLink')->assertHasNoErrors();
    $component->call('sendPasswordResetLink')->assertHasErrors(['email']);
    $this->travel(61)->seconds();
    $component->call('sendPasswordResetLink')->assertHasNoErrors();
    if ($registered) {
        Notification::assertSentToTimes(User::where('email', $email)->firstOrFail(), ResetPassword::class, 2);
    } else {
        Notification::assertNothingSent();
    }
})->with([true, false]);

test('expense dates reject non ISO input without saving', function () {
    $this->actingAs(User::factory()->create())->post('/expenses', [
        'merchant' => 'Shop', 'description' => 'Paper', 'category' => 'Office',
        'expense_date' => '10/01/2026', 'currency' => 'USD', 'amount' => '12.50',
    ])->assertSessionHasErrors('expense_date');
    $this->assertDatabaseCount('expenses', 0);
});

test('replacing a receipt removes only the old receipt after saving the replacement', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $original = 'receipts/'.$user->id.'/old.pdf';
    $other = 'receipts/other/keep.pdf';
    Storage::disk('local')->put($original, 'old receipt');
    Storage::disk('local')->put($other, 'unrelated receipt');
    $expense = Expense::factory()->for($user)->create(['receipt_path' => $original, 'receipt_original_name' => 'old.pdf']);
    $this->actingAs($user)->put('/expenses/'.$expense->id, [
        'merchant' => $expense->merchant, 'description' => $expense->description,
        'category' => $expense->category, 'expense_date' => $expense->expense_date->toDateString(),
        'currency' => $expense->currency, 'amount' => $expense->amount,
        'receipt' => UploadedFile::fake()->create('new.pdf', 1, 'application/pdf'),
    ])->assertSessionHasNoErrors()->assertRedirect('/expenses');

    Storage::disk('local')->assertMissing($original);
    Storage::disk('local')->assertExists($expense->fresh()->receipt_path);
    Storage::disk('local')->assertExists($other);
    expect($expense->fresh()->receipt_original_name)->toBe('new.pdf');
});

test('long addresses remain inside printable pages in invoice and credit note PDFs', function () {
    $user = User::factory()->create();
    $invoice = Invoice::factory()->for($user)->hasItems()->create([
        'business_address' => str_repeat('Business address line ', 200),
        'customer_address' => str_repeat('Customer address line ', 200),
    ]);
    $note = CreditNote::factory()->for($invoice)->create();
    $this->actingAs($user);
    foreach (['/invoices/'.$invoice->id.'/download', '/credit-notes/'.$note->id.'/download'] as $path) {
        $pdf = $this->get($path)->assertOk()->getContent();
        preg_match_all('/1 0 0 1 [-0-9]+ ([-0-9]+) Tm/', $pdf, $coordinates);
        expect(min(array_map('intval', $coordinates[1])))->toBeGreaterThanOrEqual(25);
        expect($pdf)->toContain('Page 2 of');
    }
});

test('failed receipt replacement preserves the original and removes the uncommitted upload', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $original = 'receipts/'.$user->id.'/original.pdf';
    Storage::disk('local')->put($original, 'original receipt');
    $expense = Expense::factory()->for($user)->create(['receipt_path' => $original]);
    Expense::saving(function (): void {
        throw new RuntimeException('Simulated database write failure');
    });
    $this->actingAs($user)->put('/expenses/'.$expense->id, [
        'merchant' => $expense->merchant, 'description' => $expense->description,
        'category' => $expense->category, 'expense_date' => $expense->expense_date->toDateString(),
        'currency' => $expense->currency, 'amount' => $expense->amount,
        'receipt' => UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf'),
    ])->assertStatus(500);

    expect($expense->fresh()->receipt_path)->toBe($original);
    expect(Storage::disk('local')->allFiles('receipts'))->toBe([$original]);
});

test('zero value invoices skip the payment question after saving', function () {
    $user = User::factory()->create(['business_profile_completed_at' => now()]);
    $invoice = Invoice::factory()->for($user)->create(['total' => '0.00', 'status' => 'paid']);
    $this->actingAs($user)->withSession(['message' => 'Document saved.'])
        ->get('/invoices/'.$invoice->id)->assertOk()
        ->assertDontSee('Have you received payment for this invoice?')
        ->assertSee('Your document is ready. Choose what to do next.')
        ->assertSee('Fully paid. Nothing left to pay.');
});

test('business page titles identify the current page in browser tabs', function () {
    $this->actingAs(User::factory()->create(['business_profile_completed_at' => now()]));
    foreach (['/dashboard' => 'Dashboard', '/reports' => 'Reports', '/settings' => 'Settings'] as $path => $title) {
        $this->get($path)->assertOk()->assertSee('<title>'.$title.'</title>', false);
    }
});

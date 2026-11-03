<?php

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('a registration code confirms the email and requires a fresh login', function () {
    Notification::fake();
    Event::fake([Verified::class]);
    Volt::test('auth.register')->set('name', 'New Client')->set('email', 'new@example.com')
        ->set('password', 'password')->set('password_confirmation', 'password')->call('register')
        ->assertHasNoErrors()->assertRedirect('/verify-email');
    $user = User::where('email', 'new@example.com')->firstOrFail();
    $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
    Notification::assertSentTo($user, VerifyEmailCode::class);

    Volt::test('auth.verify-email')->set('code', $code)->call('verifyCode')
        ->assertHasNoErrors()->assertRedirect('/login');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    expect(session('verification.user_id'))->toBeNull();
    Event::assertDispatched(Verified::class, fn ($event) => $event->user->is($user));
    $this->assertGuest();
    $this->get('/dashboard')->assertRedirect('/login');
    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login')
        ->assertHasNoErrors()->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

test('incorrect and expired codes do not verify an account', function () {
    $this->freezeTime();
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
    Notification::assertSentTo($user, VerifyEmailCode::class);
    $this->withSession(['verification.user_id' => $user->id]);

    Volt::test('auth.verify-email')->set('code', '000000')->call('verifyCode')
        ->assertHasErrors(['code'])->assertSee('The code is incorrect or has expired.');
    $this->travel(11)->minutes();
    Volt::test('auth.verify-email')->set('code', $code)->call('verifyCode')->assertHasErrors(['code']);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    $this->assertGuest();
});

test('resending a code is throttled and replaces the previous code', function () {
    $this->freezeTime();
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $oldCode = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
    $this->withSession(['verification.user_id' => $user->id]);

    Volt::test('auth.verify-email')->call('sendVerification')->assertHasErrors(['code'])
        ->assertSee('Please wait a minute before requesting another code.');
    $this->travel(61)->seconds();
    Volt::test('auth.verify-email')->call('sendVerification')->assertHasNoErrors();

    Notification::assertSentToTimes($user, VerifyEmailCode::class, 2);
    $newCode = Notification::sent($user, VerifyEmailCode::class)->last()->code;
    if ($oldCode !== $newCode) {
        Volt::test('auth.verify-email')->set('code', $oldCode)->call('verifyCode')->assertHasErrors(['code']);
    }
    Volt::test('auth.verify-email')->set('code', $newCode)->call('verifyCode')->assertHasNoErrors()->assertRedirect('/login');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('five incorrect code attempts block further guesses even after resending', function () {
    $this->freezeTime();
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $this->withSession(['verification.user_id' => $user->id]);
    $component = Volt::test('auth.verify-email')->set('code', '000000');
    foreach (range(1, 5) as $attempt) {
        $component->call('verifyCode')->assertHasErrors(['code']);
    }
    $this->travel(61)->seconds();
    $component->call('sendVerification')->assertHasNoErrors();
    $code = Notification::sent($user, VerifyEmailCode::class)->last()->code;
    Notification::assertSentToTimes($user, VerifyEmailCode::class, 2);

    $component->set('code', $code)->call('verifyCode')->assertHasErrors(['code'])
        ->assertSee('Too many attempts.');

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('another accounts code cannot verify the pending account', function () {
    Notification::fake();
    $pending = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();
    $other->sendEmailVerificationNotification();
    $code = Notification::sent($other, VerifyEmailCode::class)->sole()->code;
    Notification::assertSentTo($other, VerifyEmailCode::class);
    $this->withSession(['verification.user_id' => $pending->id]);

    Volt::test('auth.verify-email')->set('code', $code)->call('verifyCode')->assertHasErrors(['code']);

    expect($pending->fresh()->hasVerifiedEmail())->toBeFalse();
    expect($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification requires a pending registration or authenticated account', function () {
    $this->get('/verify-email')->assertRedirect('/login');
    Volt::test('auth.verify-email')->assertRedirect('/login');

    $user = User::factory()->unverified()->create();
    $this->withSession(['verification.user_id' => $user->id]);
    $component = Volt::test('auth.verify-email');
    session()->forget('verification.user_id');
    $component->set('code', '123456')->call('verifyCode')->assertRedirect('/login');
});

test('unverified users cannot access business pages or exports', function () {
    $user = User::factory()->unverified()->create();
    $this->actingAs($user);

    foreach (['dashboard', 'invoices', 'quotations', 'clients', 'expenses', 'calendar', 'analytics', 'statements', 'statements/excel', 'statements/download', 'credit-notes'] as $path) {
        $this->get('/'.$path)->assertRedirect('/verify-email');
    }
});

test('unverified users resume confirmation after entering the correct password', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->set('remember', true)
        ->call('login')->assertHasNoErrors()->assertRedirect('/verify-email');

    $this->assertGuest();
    expect(session('verification.user_id'))->toBe($user->id);
    Notification::assertSentTo($user, VerifyEmailCode::class);
    $this->get('/dashboard')->assertRedirect('/login');
});

test('confirmation codes are tied to the email address they were issued for', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();
    $code = Notification::sent($user, VerifyEmailCode::class)->sole()->code;
    Notification::assertSentTo($user, VerifyEmailCode::class);
    $user->update(['email' => 'changed@example.com']);
    $this->withSession(['verification.user_id' => $user->id]);

    Volt::test('auth.verify-email')->set('code', $code)->call('verifyCode')->assertHasErrors(['code']);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('confirmation emails contain the code and expiry instructions', function () {
    $user = User::factory()->unverified()->make();
    $notification = new VerifyEmailCode('123456');

    $mail = $notification->toMail($user);

    expect($mail->subject)->toBe('Confirm your My Invoice Tracker email');
    expect(implode(' ', $mail->introLines))->toContain('123456')->toContain('10 minutes');
});

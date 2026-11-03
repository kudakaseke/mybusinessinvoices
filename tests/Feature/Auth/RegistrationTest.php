<?php

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200);
});

test('new users can register', function () {
    Notification::fake();

    $response = Volt::test('auth.register')
        ->set('name', 'My Studio')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('verification.notice', absolute: false));

    $this->assertGuest();
    $user = User::where('email', 'test@example.com')->firstOrFail();
    expect($user->business_name)->toBe('My Studio');
    expect($user->hasVerifiedEmail())->toBeFalse();
    expect(session('verification.user_id'))->toBe($user->id);
    Notification::assertSentTo($user, VerifyEmailCode::class, fn ($notification) => preg_match('/^[0-9]{6}$/', $notification->code) === 1);
});

<?php

use App\Models\User;
use App\Notifications\AccountDeletionCode;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

test('settings page is displayed', function (string $page) {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/'.$page)->assertOk();
})->with(['profile', 'password', 'appearance']);

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email_verified_at)->not->toBeNull();
});

test('profile email cannot be changed through a modified request', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;
    $verifiedAt = $user->email_verified_at;
    $this->actingAs($user);

    Volt::test('settings.profile')->set('email', 'changed@example.com')
        ->call('updateProfileInformation')->assertHasErrors(['email' => 'in']);

    expect($user->fresh()->email)->toBe($originalEmail);
    expect($user->fresh()->email_verified_at->equalTo($verifiedAt))->toBeTrue();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    Notification::fake();
    Storage::fake('local');
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'password')
        ->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    Notification::assertSentTo($user, AccountDeletionCode::class);
    $response->set('code', $code)->call('deleteUser');

    $response
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
    expect(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Volt::test('settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('sendDeletionCode');

    $response->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

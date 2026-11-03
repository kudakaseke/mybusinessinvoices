<?php

use App\AccountDeletionCodes;
use App\Models\User;
use App\Notifications\AccountDeletionCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;
use Symfony\Component\Mailer\Exception\TransportException;

test('confirming the password sends a six digit deletion email without deleting the account', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')
        ->assertHasNoErrors()->assertSet('codeSent', true)->assertSet('password', '')->assertSee('Deletion confirmation code');

    Notification::assertSentTo($user, AccountDeletionCode::class, fn ($notification): bool => preg_match('/^[0-9]{6}$/D', $notification->code) === 1);
    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
});

test('an incorrect password does not send a deletion code', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.delete-user-form')->set('password', 'wrong-password')->call('sendDeletionCode')->assertHasErrors('password');

    Notification::assertNothingSent();
    $this->assertModelExists($user);
});

test('the deletion action cannot bypass password and email confirmation', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('settings.delete-user-form')->set('password', 'password')->set('code', '123456')->call('deleteUser')
        ->assertHasErrors('code')->assertSee('The deletion code is incorrect or has expired.');

    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
});

test('incorrect and expired deletion codes keep the account active', function () {
    $this->freezeTime();
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;

    $component->set('code', '000000')->call('deleteUser')->assertHasErrors('code');
    $this->travel(11)->minutes();
    $component->set('code', $code)->call('deleteUser')->assertHasErrors('code');

    $this->assertModelExists($user);
    $this->assertAuthenticatedAs($user);
});

test('a deletion code from another account cannot delete the current account', function () {
    Notification::fake();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);
    Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    $this->actingAs($other);

    Volt::test('settings.delete-user-form')->set('code', $code)->call('deleteUser')->assertHasErrors('code');

    $this->assertModelExists($user);
    $this->assertModelExists($other);
});

test('a deletion code cannot be used from a different session', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    session()->migrate();

    Volt::test('settings.delete-user-form')->set('code', $code)->call('deleteUser')->assertHasErrors('code');

    $this->assertModelExists($user);
});

test('changing the password invalidates a pending deletion code', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    $user->forceFill(['password' => Hash::make('new-password')])->save();

    $component->set('code', $code)->call('deleteUser')->assertHasErrors('code');

    $this->assertModelExists($user);
});

test('cancelling deletion invalidates the code and requires the password again', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;

    $component->call('restartDeletion')->assertSet('codeSent', false)->assertSet('password', '')->assertSet('code', '');
    $component->set('code', $code)->call('deleteUser')->assertHasErrors('code');

    $this->assertModelExists($user);
});

test('deletion email requests are throttled even when restarting the form', function () {
    $this->freezeTime();
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();

    $component->call('restartDeletion')->set('password', 'password')->call('sendDeletionCode')->assertHasErrors('code');
    $this->travel(61)->seconds();
    $component->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors()->assertSet('codeSent', true);

    Notification::assertSentToTimes($user, AccountDeletionCode::class, 2);
    $this->assertModelExists($user);
});

test('five incorrect guesses block a valid deletion code even after requesting another', function () {
    $this->freezeTime();
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    foreach (range(1, 5) as $attempt) {
        $component->set('code', '000000')->call('deleteUser')->assertHasErrors('code');
    }
    $component->call('restartDeletion');
    $this->travel(61)->seconds();
    $component->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->last()->code;

    $component->set('code', $code)->call('deleteUser')->assertHasErrors('code')->assertSee('Too many attempts.');

    $this->assertModelExists($user);
});

test('a verified deletion code cannot be reused', function () {
    Notification::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $component = Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')->assertHasNoErrors();
    $code = Notification::sent($user, AccountDeletionCode::class)->sole()->code;
    app(AccountDeletionCodes::class)->verify($user, session()->getId(), $code);

    $component->set('code', $code)->call('deleteUser')->assertHasErrors('code');

    $this->assertModelExists($user);
});

test('a failed email does not enable account deletion', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    Notification::shouldReceive('send')->once()->andThrow(new TransportException('Test mail outage'));

    Volt::test('settings.delete-user-form')->set('password', 'password')->call('sendDeletionCode')
        ->assertHasErrors('code')->assertSet('codeSent', false)->assertSet('password', '')->assertSee('We could not send your deletion code.');

    expect(Cache::get('account-deletion:'.$user->id.':'.hash('sha256', session()->getId())))->toBeNull();
    $this->assertModelExists($user);
});

test('account deletion confirmation requires an authenticated verified user', function () {
    Volt::test('settings.delete-user-form')->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create());

    Volt::test('settings.delete-user-form')->assertForbidden();
});

test('the deletion email includes the confirmation code and explains its purpose and expiry', function () {
    $user = User::factory()->make();

    $mail = (new AccountDeletionCode('123456'))->toMail($user);

    expect((string) $mail->render())->toContain('123456')->toContain('10 minutes')->toContain('permanently delete your account');
    expect($mail->subject)->toContain('account deletion');
});

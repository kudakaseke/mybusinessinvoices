<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('first login shows business setup until it is completed', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')
        ->call('login')->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertOk()->assertSeeLivewire('business-setup')->assertSee('Set up your business');
    $this->get('/calendar')->assertOk()->assertSeeLivewire('business-setup');
    expect($user->fresh()->business_profile_completed_at)->toBeNull();
});

test('setup saves business details without requiring a website or WhatsApp number', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('business-setup')->assertSet('business_email', $user->email)
        ->set('business_name', 'My Studio')->set('business_phone', '+263771234567')
        ->set('business_address', '10 Main Street, Harare')->call('save')
        ->assertHasNoErrors()->assertRedirect('/dashboard');

    $user->refresh();
    expect($user->business_name)->toBe('My Studio');
    expect($user->business_email)->toBe($user->email);
    expect($user->business_phone)->toBe('+263771234567');
    expect($user->business_address)->toBe('10 Main Street, Harare');
    expect($user->website)->toBeNull();
    expect($user->whatsapp_number)->toBeNull();
    expect($user->business_profile_completed_at)->not->toBeNull();
    $this->get('/dashboard')->assertOk()->assertDontSeeLivewire('business-setup');
    $this->get('/invoices/create')->assertOk()->assertSee('My Studio')->assertSee('10 Main Street, Harare');
});

test('later logins do not show setup after completing the profile', function () {
    $user = User::factory()->create(['business_name' => 'Existing Business', 'business_profile_completed_at' => now()]);

    Volt::test('auth.login')->set('email', $user->email)->set('password', 'password')->call('login')->assertRedirect('/dashboard');

    $this->get('/dashboard')->assertOk()->assertDontSeeLivewire('business-setup');
});

test('required business details must be filled before completing setup', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Volt::test('business-setup')->set('business_email', '')->call('save')
        ->assertHasErrors(['business_name' => 'required', 'business_email' => 'required', 'business_phone' => 'required', 'business_address' => 'required']);

    expect($user->fresh()->business_profile_completed_at)->toBeNull();
});

test('an optional website must be a valid web address when provided', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $component = Volt::test('business-setup')->set('business_name', 'My Studio')
        ->set('business_phone', '0771234567')->set('business_address', 'Harare');

    $component->set('website', 'not-a-website')->call('save')->assertHasErrors(['website' => 'url']);
    expect($user->fresh()->business_profile_completed_at)->toBeNull();
    $component->set('website', 'https://example.com')->set('whatsapp_number', '0771234567')
        ->call('save')->assertHasNoErrors()->assertRedirect('/dashboard');
    expect($user->fresh()->website)->toBe('https://example.com');
    expect($user->fresh()->whatsapp_number)->toBe('0771234567');
});

test('setup updates only the authenticated business', function () {
    $user = User::factory()->create();
    $other = User::factory()->create(['business_name' => 'Private Business']);
    $this->actingAs($user);

    Volt::test('business-setup')->set('business_name', 'My Studio')->set('business_phone', '0771234567')
        ->set('business_address', 'Harare')->call('save')->assertHasNoErrors();

    expect($other->fresh()->business_name)->toBe('Private Business');
    expect($other->fresh()->business_profile_completed_at)->toBeNull();
});

test('setup requires an authenticated and verified account', function () {
    Volt::test('business-setup')->assertForbidden();
    $this->actingAs(User::factory()->unverified()->create());
    Volt::test('business-setup')->assertForbidden();
});

test('business setup cannot replace the verified email address', function () {
    $user = User::factory()->create();
    $originalEmail = $user->email;
    $this->actingAs($user);

    Volt::test('business-setup')->set('business_name', 'My Studio')->set('business_phone', '0771234567')
        ->set('business_address', 'Harare')->set('business_email', 'changed@example.com')
        ->call('save')->assertHasErrors(['business_email' => 'in']);

    expect($user->fresh()->email)->toBe($originalEmail);
    expect($user->fresh()->business_email)->toBeNull();
    expect($user->fresh()->business_profile_completed_at)->toBeNull();
});

test('business settings reject changing or clearing an existing email', function () {
    $user = User::factory()->create(['business_email' => 'business@example.com', 'business_profile_completed_at' => now()]);
    $this->actingAs($user);
    $data = ['business_name' => 'My Studio', 'base_currency' => 'USD', 'target_currency' => 'ZWG',
        'exchange_rate' => '27.5', 'default_tax_rate' => '15', 'default_currencies' => ['USD', 'ZWG']];

    foreach (['changed@example.com', ''] as $email) {
        $this->put('/settings', $data + ['business_email' => $email])->assertSessionHasErrors('business_email');
        expect($user->fresh()->business_email)->toBe('business@example.com');
    }

    $this->put('/settings', $data + ['business_email' => 'business@example.com'])->assertSessionHasNoErrors();
    expect($user->fresh()->business_name)->toBe('My Studio');
    expect($user->fresh()->business_email)->toBe('business@example.com');
});

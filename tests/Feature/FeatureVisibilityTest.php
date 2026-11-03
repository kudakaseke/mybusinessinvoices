<?php

use App\Models\DocumentDraft;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;

function featureVisibilitySettings(): array
{
    return ['base_currency' => 'USD', 'target_currency' => 'ZWG', 'exchange_rate' => '26', 'default_tax_rate' => '0', 'default_currencies' => ['USD', 'ZWG']];
}

test('quotations are visible by default and business settings offer a visibility control', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/settings')->assertOk()->assertSee('Features to show')
        ->assertSee('name="quotations_enabled"', false);
    $this->get('/dashboard')->assertOk()->assertSee('href="'.route('documents.index', 'quotations').'"', false);

    expect($user->fresh()->quotations_enabled)->toBeTrue();
});

test('a business can hide and restore quotations without losing records or affecting another business', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $quotation = Quotation::factory()->for($user)->create(['issue_date' => '2026-11-03']);
    $draft = DocumentDraft::factory()->create(['user_id' => $user->id, 'kind' => 'quotations', 'payload' => ['quotation_number' => 'QUO-SAVED-DRAFT']]);

    $this->actingAs($user)->from('/settings')->put('/settings', featureVisibilitySettings() + ['quotations_enabled' => '0', 'user_id' => $other->id])
        ->assertSessionHasNoErrors()->assertRedirect('/settings');

    expect($user->fresh()->quotations_enabled)->toBeFalse();
    expect($other->fresh()->quotations_enabled)->toBeTrue();
    $this->get('/dashboard')->assertOk()->assertDontSee('href="'.route('documents.index', 'quotations').'"', false)
        ->assertViewHas('counts', fn (array $counts): bool => ! array_key_exists('Quotations', $counts));
    $this->get('/calendar?month=2026-11')->assertOk()->assertViewHas('events', fn ($events): bool => $events->flatten(1)->where('type', 'quotations')->isEmpty());
    $this->get('/drafts')->assertOk()->assertDontSee('QUO-SAVED-DRAFT');
    $this->assertModelExists($quotation);
    $this->assertModelExists($draft);

    $this->put('/settings', featureVisibilitySettings() + ['quotations_enabled' => '1'])->assertSessionHasNoErrors();

    expect($user->fresh()->quotations_enabled)->toBeTrue();
    $this->get('/dashboard')->assertOk()->assertSee('href="'.route('documents.index', 'quotations').'"', false)
        ->assertViewHas('counts', fn (array $counts): bool => $counts['Quotations'] === 1);
    $this->get('/calendar?month=2026-11')->assertOk()->assertViewHas('events', fn ($events): bool => $events->flatten(1)->where('type', 'quotations')->count() === 1);
    $this->get('/drafts')->assertOk()->assertSee('QUO-SAVED-DRAFT');
});

test('invalid quotation visibility does not change the business preference', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put('/settings', featureVisibilitySettings() + ['quotations_enabled' => 'invalid'])
        ->assertSessionHasErrors('quotations_enabled');

    expect($user->fresh()->quotations_enabled)->toBeTrue();
});

test('saving other settings keeps the current quotation visibility when omitted', function () {
    $user = User::factory()->create(['quotations_enabled' => false]);

    $this->actingAs($user)->put('/settings', featureVisibilitySettings())->assertSessionHasNoErrors();

    expect($user->fresh()->quotations_enabled)->toBeFalse();
});

test('settings organise all optional features into named groups', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/settings')->assertOk()
        ->assertSeeInOrder(['Main</legend>', 'Finance</legend>', 'Manage</legend>'], false);

    foreach (['analytics', 'reports', 'calendar', 'saved_drafts', 'statements', 'credit_notes', 'products', 'clients'] as $feature) {
        $response->assertSee('name="feature_preferences['.$feature.']"', false);
        expect($user->showsFeature($feature))->toBeTrue();
    }
});

test('each optional menu item can be hidden and restored independently per business', function (string $feature, string $path) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('href="'.url($path).'"', false);

    $this->put('/settings', featureVisibilitySettings() + ['feature_preferences' => [$feature => '0']])->assertSessionHasNoErrors();

    expect($user->fresh()->showsFeature($feature))->toBeFalse();
    expect($other->fresh()->showsFeature($feature))->toBeTrue();
    $this->get('/dashboard')->assertOk()->assertDontSee('href="'.url($path).'"', false)
        ->assertSee('href="'.route('settings').'"', false)->assertSee('Create invoice');

    $this->put('/settings', featureVisibilitySettings() + ['feature_preferences' => [$feature => '1']])->assertSessionHasNoErrors();

    expect($user->fresh()->showsFeature($feature))->toBeTrue();
    $this->get('/dashboard')->assertOk()->assertSee('href="'.url($path).'"', false);
})->with([
    ['analytics', '/analytics'], ['reports', '/reports'], ['calendar', '/calendar'],
    ['saved_drafts', '/drafts'], ['statements', '/statements'], ['credit_notes', '/credit-notes'],
    ['products', '/products'], ['clients', '/clients'],
]);

test('hidden features also hide related shortcuts without affecting invoice balances', function () {
    $user = User::factory()->create(['feature_preferences' => ['statements' => false, 'saved_drafts' => false, 'clients' => false, 'products' => false, 'credit_notes' => false]]);
    $invoice = Invoice::factory()->for($user)->create(['issue_date' => '2026-11-03', 'total' => '100.00', 'status' => 'outstanding']);

    $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Generate client report')
        ->assertViewHas('counts', fn (array $counts): bool => ! array_key_exists('Clients', $counts) && ! array_key_exists('Products', $counts));
    $this->get('/invoices/create')->assertOk()->assertDontSee('Save draft')->assertDontSee('Choose a saved client')
        ->assertDontSee('Saved product for item')->assertSee('name="customer_name"', false);
    $this->get('/invoices/'.$invoice->id)->assertOk()->assertDontSee('View client statement')->assertDontSee('Issue a credit note');
    $this->get('/calendar?month=2026-11')->assertOk()->assertDontSee('href="'.route('statements'), false)->assertSee('Total Invoice:');

    expect($invoice->fresh()->balanceDue())->toBe('100.00');
    $this->assertModelExists($invoice);
});

test('invalid feature preferences are rejected without changing saved choices', function (array $preferences, string $error) {
    $user = User::factory()->create(['feature_preferences' => ['analytics' => false]]);

    $this->actingAs($user)->put('/settings', featureVisibilitySettings() + ['feature_preferences' => $preferences])
        ->assertSessionHasErrors($error);

    expect($user->fresh()->feature_preferences)->toBe(['analytics' => false]);
})->with([
    [['analytics' => 'invalid'], 'feature_preferences.analytics'],
    [['admin' => true], 'feature_preferences'],
]);

test('partial settings updates retain omitted feature preferences', function () {
    $user = User::factory()->create(['feature_preferences' => ['analytics' => false, 'reports' => false]]);

    $this->actingAs($user)->put('/settings', featureVisibilitySettings() + ['feature_preferences' => ['reports' => true]])
        ->assertSessionHasNoErrors();
    $this->put('/settings', featureVisibilitySettings())->assertSessionHasNoErrors();

    expect($user->fresh()->feature_preferences)->toBe(['analytics' => false, 'reports' => true]);
});

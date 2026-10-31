<?php

use App\Models\User;

function rateLimitedLivewirePayload(string $html): array
{
    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
    expect($matches)->toHaveKey(1);

    return ['components' => [['snapshot' => html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'updates' => [], 'calls' => []]]];
}

test('page limits are shared across routes per account and recover after a minute', function () {
    config(['rate-limits.requests.authenticated' => 2]);
    $this->travelTo(now()->startOfMinute());
    $user = User::factory()->create();

    $this->actingAs($user)->get('/clients')->assertOk();
    $this->get('/products')->assertOk();
    $this->getJson('/expenses')->assertTooManyRequests()->assertHeader('Retry-After', '60');

    $this->travel(61)->seconds();
    $this->get('/clients')->assertOk();
});

test('guest limits follow the IP address and do not block other visitors', function () {
    config(['rate-limits.requests.guest' => 1]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('/')->assertOk();
    $this->get('/login')->assertTooManyRequests()->assertHeader('Retry-After');
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->get('/login')->assertOk();
});

test('authenticated limits follow accounts across IP changes and keep accounts independent', function () {
    config(['rate-limits.requests.authenticated' => 1]);
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($first)->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->get('/clients')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->get('/products')->assertTooManyRequests();
    $this->actingAs($second)->get('/clients')->assertOk();
});

test('write limits prevent additional records while allowing normal browsing', function () {
    config(['rate-limits.writes.authenticated' => 1]);
    $user = User::factory()->create();
    $payload = ['merchant' => 'Shop', 'description' => 'Food', 'category' => 'Food', 'expense_date' => '2026-10-30', 'currency' => 'USD', 'amount' => '5.00'];

    $this->actingAs($user)->post('/expenses', $payload)->assertSessionHasNoErrors()->assertRedirect();
    $this->postJson('/expenses', array_replace($payload, ['description' => 'Another expense']))->assertTooManyRequests()->assertHeader('Retry-After');
    $this->postJson('/clients', ['name' => 'Client', 'email' => 'client@example.com'])->assertTooManyRequests();
    $this->get('/expenses')->assertOk();

    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseCount('clients', 0);
});

test('exports downloads and previews share a stricter budget', function (string $method, string $path) {
    config(['rate-limits.exports' => 1]);

    $this->actingAs(User::factory()->create())->get('/statements/download')->assertOk();
    $this->call($method, $path)->assertTooManyRequests()->assertHeader('Retry-After');
    $this->get('/statements')->assertOk();
})->with([
    ['GET', '/statements/excel'], ['GET', '/invoices/1/download'], ['GET', '/quotations/1/download'],
    ['GET', '/credit-notes/1/download'], ['GET', '/expenses/1/receipt'], ['POST', '/invoices/review'],
]);

test('write limits also protect payment credit note and settings changes', function (string $method, string $path) {
    config(['rate-limits.writes.authenticated' => 1]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/expenses', [])->assertUnprocessable();
    $this->call($method, $path)->assertTooManyRequests();

    $this->assertDatabaseCount('expenses', 0);
    $this->assertDatabaseCount('invoice_payments', 0);
    $this->assertDatabaseCount('credit_notes', 0);
})->with([['PATCH', '/invoices/1/payment'], ['POST', '/invoices/1/credit-notes'], ['PUT', '/settings']]);

test('Livewire guest submissions cannot bypass the application write limit', function () {
    config(['rate-limits.writes.guest' => 1]);
    $endpoint = route('default-livewire.update');
    $payload = rateLimitedLivewirePayload($this->get('/login')->assertOk()->getContent());

    $this->withHeader('X-Livewire', '1')->postJson($endpoint, $payload)->assertOk();
    $this->postJson($endpoint, $payload)->assertTooManyRequests()->assertHeader('Retry-After');
});

test('Livewire authenticated submissions use account write limits', function () {
    config(['rate-limits.writes.authenticated' => 1]);
    $endpoint = route('default-livewire.update');
    $this->actingAs(User::factory()->create());
    $payload = rateLimitedLivewirePayload($this->get('/settings/profile')->assertOk()->getContent());

    $this->withHeader('X-Livewire', '1')->postJson($endpoint, $payload)->assertOk();
    $this->postJson($endpoint, $payload)->assertTooManyRequests();
});

<?php

use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;

test('default document numbers use month year and a three digit sequence', function (string $kind, string $prefix) {
    $this->travelTo(now('Africa/Johannesburg')->setDate(2026, 9, 15));
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/'.$kind.'/create')->assertOk();

    $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
    expect($response->viewData('document')->$number)->toBe($prefix.'09/26-001');
})->with([['invoices', 'INV-'], ['quotations', 'QUO-']]);

test('number suggestions follow the highest own monthly sequence rather than the latest document', function (string $kind, string $prefix) {
    $this->travelTo(now('Africa/Johannesburg')->setDate(2026, 9, 15));
    $user = User::factory()->create();
    $model = $kind === 'invoices' ? Invoice::class : Quotation::class;
    $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
    $model::factory()->for($user)->create([$number => $prefix.'09/26-012']);
    $model::factory()->for($user)->create([$number => $prefix.'09/26-002']);
    $model::factory()->for($user)->create([$number => $prefix.'08/26-999']);
    $model::factory()->for($user)->create([$number => 'CUSTOM-5000']);
    $model::factory()->create([$number => $prefix.'09/26-999']);

    $response = $this->actingAs($user)->get('/'.$kind.'/create')->assertOk();

    expect($response->viewData('document')->$number)->toBe($prefix.'09/26-013');
})->with([['invoices', 'INV-'], ['quotations', 'QUO-']]);

test('document numbering resets when the month changes and does not renumber saved records', function (string $kind, string $prefix) {
    $this->travelTo(now('Africa/Johannesburg')->setDate(2026, 10, 1));
    $user = User::factory()->create();
    $model = $kind === 'invoices' ? Invoice::class : Quotation::class;
    $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
    $document = $model::factory()->for($user)->create([$number => $prefix.'09/26-025']);

    $response = $this->actingAs($user)->get('/'.$kind.'/create')->assertOk();

    expect($response->viewData('document')->$number)->toBe($prefix.'10/26-001');
    expect($document->fresh()->$number)->toBe($prefix.'09/26-025');
})->with([['invoices', 'INV-'], ['quotations', 'QUO-']]);

test('document numbering can continue beyond 999', function (string $kind, string $prefix) {
    $this->travelTo(now('Africa/Johannesburg')->setDate(2026, 9, 15));
    $user = User::factory()->create();
    $model = $kind === 'invoices' ? Invoice::class : Quotation::class;
    $number = $kind === 'invoices' ? 'invoice_number' : 'quotation_number';
    $model::factory()->for($user)->create([$number => $prefix.'09/26-999']);

    $response = $this->actingAs($user)->get('/'.$kind.'/create')->assertOk();

    expect($response->viewData('document')->$number)->toBe($prefix.'09/26-1000');
})->with([['invoices', 'INV-'], ['quotations', 'QUO-']]);

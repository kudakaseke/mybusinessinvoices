<?php

use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

function logoUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('logo.jpg', file_get_contents(public_path('accounting-hero.jpg')));
}

function logoSettings(User $user): array
{
    return ['business_name' => 'Studio', 'business_email' => $user->email, 'base_currency' => 'USD', 'target_currency' => 'ZWG', 'exchange_rate' => '27.5', 'default_tax_rate' => '0', 'default_currencies' => ['USD']];
}

test('setup accepts an optional business logo and rejects non images', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);
    $setup = Volt::test('business-setup')->set('name', 'Jane')->set('surname', 'Doe')->set('business_name', 'Studio')
        ->set('business_phone', '123456')->set('business_address', 'Harare');
    $setup->set('business_logo', UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'))->call('save')->assertHasErrors('business_logo');
    $setup->set('business_logo', logoUpload())->call('save')->assertHasNoErrors();
    Storage::disk('local')->assertExists($user->fresh()->business_logo_path);
});

test('settings replaces and removes a logo and rejects other files', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $this->actingAs($user);
    $this->put('/settings', logoSettings($user) + ['business_logo' => logoUpload()])->assertSessionHasNoErrors();
    $old = $user->fresh()->business_logo_path;
    Storage::disk('local')->assertExists($old);
    $this->put('/settings', logoSettings($user) + ['business_logo' => UploadedFile::fake()->create('bad.pdf', 10, 'application/pdf')])->assertSessionHasErrors('business_logo');
    expect($user->fresh()->business_logo_path)->toBe($old);
    $this->put('/settings', logoSettings($user) + ['business_logo' => logoUpload()])->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing($old);
    $new = $user->fresh()->business_logo_path;
    Storage::disk('local')->assertExists($new);
    $this->put('/settings', logoSettings($user) + ['remove_business_logo' => '1'])->assertSessionHasNoErrors();
    Storage::disk('local')->assertMissing($new);
    expect($user->fresh()->business_logo_path)->toBeNull();
});

test('document downloads embed the logo and fall back to the business name', function (string $kind, string $model) {
    Storage::fake('local');
    $user = User::factory()->create();
    $document = $model::factory()->for($user)->create(['business_name' => 'Studio Company']);
    $this->actingAs($user);
    $this->get('/'.$kind.'/'.$document->id.'/download')->assertOk()->assertSee('STUDIO COMPANY', false)->assertDontSee('/Subtype /Image', false);
    $user->update(['business_logo_path' => logoUpload()->store('business-logos/'.$user->id, 'local')]);
    $this->get('/'.$kind.'/'.$document->id.'/download')->assertOk()->assertSee('/Subtype /Image', false)->assertSee('/Logo Do', false);
})->with([['invoices', Invoice::class], ['quotations', Quotation::class]]);

<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $showSetup = true;

    public string $name = '';

    public string $surname = '';

    public string $business_name = '';

    public string $business_email = '';

    public string $business_phone = '';

    public string $business_address = '';

    public string $whatsapp_number = '';

    public string $website = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasVerifiedEmail(), 403);
        $user = Auth::user();
        foreach (['business_name', 'business_phone', 'business_address', 'whatsapp_number', 'website'] as $field) {
            $this->$field = $user->$field ?? '';
        }
        $this->business_email = $user->business_email ?? $user->email;
    }

    public function save(): void
    {
        abort_unless(Auth::user()?->hasVerifiedEmail(), 403);
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:127'],
            'surname' => ['required', 'string', 'max:127'],
            'business_name' => ['required', 'string', 'max:255'],
            'business_email' => ['required', 'email', 'max:255', Rule::in([Auth::user()->business_email ?? Auth::user()->email])],
            'business_phone' => ['required', 'string', 'max:255'],
            'business_address' => ['required', 'string', 'max:10000'],
            'whatsapp_number' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
        ]);
        foreach (['website', 'whatsapp_number'] as $field) {
            $validated[$field] = filled($validated[$field]) ? $validated[$field] : null;
        }

        $user = Auth::user();
        $validated['name'] = trim($validated['name']).' '.trim($validated['surname']);
        unset($validated['surname']);
        $user->fill($validated);
        $user->business_profile_completed_at ??= now();
        $user->save();
        session()->flash('message', 'Your business details are ready. You can update them in Settings.');
        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <flux:modal name="business-setup" wire:model="showSetup" :dismissible="false" :escapable="false" :closable="false" class="w-full max-w-xl">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">Set up your business</flux:heading>
                <flux:text class="mt-2">Welcome! Add your personal and business details before getting started. Your business details will appear on your invoices and quotations.</flux:text>
            </div>
            <fieldset class="space-y-5">
                <legend class="mb-3 text-sm font-semibold">Personal info</legend>
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="name" label="Name" required maxlength="127" autocomplete="given-name" autofocus />
                    <flux:input wire:model="surname" label="Surname" required maxlength="127" autocomplete="family-name" />
                </div>
            </fieldset>
            <fieldset class="space-y-5">
                <legend class="mb-3 text-sm font-semibold">Business info</legend>
                <flux:input wire:model="business_name" label="Business name" required autocomplete="organization" />
                <flux:input wire:model="business_email" label="Business email" type="email" readonly />
                <flux:input wire:model="business_phone" label="Business phone" type="tel" required autocomplete="tel" />
                <flux:textarea wire:model="business_address" label="Business address" required autocomplete="street-address" />
                <div class="grid gap-5 sm:grid-cols-2">
                    <flux:input wire:model="whatsapp_number" label="WhatsApp number (optional)" type="tel" />
                    <flux:input wire:model="website" label="Website (optional)" type="url" placeholder="https://example.com" />
                </div>
                <flux:text>You can leave Website and WhatsApp blank and update them later in Settings.</flux:text>
            </fieldset>
            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Save and continue</flux:button>
            </div>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-sm text-zinc-500 underline dark:text-zinc-400">Sign out</button>
        </form>
    </flux:modal>
</div>

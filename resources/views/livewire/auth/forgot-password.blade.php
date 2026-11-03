<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        session()->forget('status');
        $this->resetValidation();
        $key = 'password-recovery:'.hash('sha256', mb_strtolower($this->email).'|'.request()->ip());
        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages(['email' => 'Please wait a minute before requesting another reset link.']);
        }
        RateLimiter::hit($key, 60);
        try {
            Password::sendResetLink($this->only('email'));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            throw ValidationException::withMessages(['email' => 'We could not send the reset email. Please wait a minute and try again.']);
        }

        session()->flash('status', __('A reset link will be sent if the account exists.'));
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Forgot password" description="Enter your email to receive a password reset link" />

    <!-- Session Status -->
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-6">
        <!-- Email Address -->
        <div class="grid gap-2">
            <flux:input wire:model="email" label="{{ __('Email Address') }}" type="email" name="email" required autofocus placeholder="email@example.com" />
        </div>

        <flux:button variant="primary" type="submit" class="w-full">{{ __('Email password reset link') }}</flux:button>
    </form>

    <div class="space-x-1 text-center text-sm text-zinc-400">
        Or, return to
        <x-text-link href="{{ route('login') }}">log in</x-text-link>
    </div>
</div>

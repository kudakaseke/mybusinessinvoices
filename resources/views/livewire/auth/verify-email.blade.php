<?php

use App\EmailVerificationCodes;
use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] class extends Component {
    public string $code = '';

    public function mount(): void
    {
        $user = $this->verificationUser();
        if (! $user) {
            $this->redirect(route('login', absolute: false), navigate: true);
        } elseif ($user->hasVerifiedEmail()) {
            $this->redirect(route(Auth::check() ? 'dashboard' : 'login', absolute: false), navigate: true);
        }
    }

    public function verifyCode(): void
    {
        $user = $this->verificationUser();
        if (! $user) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        $this->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        app(EmailVerificationCodes::class)->verify($user, $this->code);
        $this->finishVerification();
    }

    public function sendVerification(): void
    {
        $user = $this->verificationUser();
        if (! $user) {
            $this->redirect(route('login', absolute: false), navigate: true);

            return;
        }

        if ($user->hasVerifiedEmail()) {
            $this->redirect(route(Auth::check() ? 'dashboard' : 'login', absolute: false), navigate: true);

            return;
        }

        app(EmailVerificationCodes::class)->send($user, resend: true);
        $this->reset('code');
        $this->resetValidation();
        session()->flash('status', 'A new confirmation code has been emailed to you.');
    }

    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect(route('login', absolute: false), navigate: true);
    }

    protected function verificationUser(): ?User
    {
        return Auth::user() ?? User::find(session('verification.user_id'));
    }

    protected function finishVerification(): void
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        session()->flash('status', 'Email confirmed successfully. Please log in with your email and password.');
        $this->redirect(route('login', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Confirm your email" description="Enter the six-digit code sent to your email address. The code expires in 10 minutes." />
    <x-auth-session-status class="text-center" :status="session('status')" />

    <form wire:submit="verifyCode" class="flex flex-col gap-6">
        <flux:input wire:model="code" label="Confirmation code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" required autofocus />
        <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
            Confirm email
        </flux:button>
    </form>

    <flux:button wire:click="sendVerification" class="w-full" wire:loading.attr="disabled">
        Resend confirmation code
    </flux:button>
    <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">Check your spam folder too. After confirmation, you will be taken to the login page.</p>
    <button wire:click="logout" type="button" class="text-center text-sm underline">
        Back to login
    </button>
</div>

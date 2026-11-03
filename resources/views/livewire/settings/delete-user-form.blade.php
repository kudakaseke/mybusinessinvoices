<?php

use App\AccountDeletionCodes;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new class extends Component {
    public string $password = '';
    public string $code = '';

    #[Locked]
    public bool $codeSent = false;

    public function mount(): void
    {
        $this->deletionUser();
    }

    public function sendDeletionCode(): void
    {
        $user = $this->deletionUser();
        $this->resetValidation();
        try {
            $this->validate(['password' => ['required', 'string', 'current_password']]);
            app(AccountDeletionCodes::class)->send($user, session()->getId());
        } finally {
            $this->reset('password');
        }
        $this->reset('code');
        $this->codeSent = true;
    }

    public function restartDeletion(): void
    {
        app(AccountDeletionCodes::class)->forget($this->deletionUser(), session()->getId());
        $this->reset('password', 'code', 'codeSent');
        $this->resetValidation();
    }

    protected function deletionUser(): User
    {
        abort_unless(Auth::check() && Auth::user()->hasVerifiedEmail(), 403);

        return Auth::user();
    }

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(): void
    {
        $user = $this->deletionUser();
        $this->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);
        app(AccountDeletionCodes::class)->verify($user, session()->getId(), $this->code);
        try {
            DB::transaction(fn () => $user->delete());
        } catch (RuntimeException $exception) {
            report($exception);
            $this->reset('password', 'code', 'codeSent');
            $this->addError('password', 'We could not delete your account. Please try again.');

            return;
        }
        Auth::guard('web')->logoutCurrentDevice();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <flux:heading>{{ __('Delete Account') }}</flux:heading>
        <flux:subheading>{{ __('Delete your account and all of its resources') }}</flux:subheading>
    </div>

    <flux:modal.trigger name="confirm-user-deletion">
        <flux:button variant="danger" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
            {{ __('Delete Account') }}
        </flux:button>
    </flux:modal.trigger>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="{{ $codeSent ? 'deleteUser' : 'sendDeletionCode' }}" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Confirm your password, then enter the six-digit code emailed to you.') }}
                </flux:subheading>
            </div>

            @if($codeSent)
                <p class="text-sm text-zinc-500">A six-digit code was sent to {{ auth()->user()->email }}. It expires in 10 minutes.</p>
                <flux:input wire:model="code" label="Deletion confirmation code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus />
                <flux:button type="button" variant="filled" wire:click="restartDeletion" wire:loading.attr="disabled">Request a new code</flux:button>
            @else
                <flux:input wire:model="password" id="password" label="{{ __('Password') }}" type="password" name="password" autocomplete="current-password" required />
                <flux:error name="code" />
            @endif

            <div class="flex justify-end space-x-2">
                <flux:modal.close>
                    <flux:button type="button" variant="filled" wire:click="restartDeletion">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit" wire:loading.attr="disabled">{{ $codeSent ? __('Delete Account') : __('Send confirmation code') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</section>

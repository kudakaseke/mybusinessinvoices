<?php

namespace App;

use App\Models\User;
use App\Notifications\AccountDeletionCode;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class AccountDeletionCodes
{
    public function send(User $user, string $sessionId): void
    {
        $sendKey = 'account-deletion:'.$user->id.':send';
        if (RateLimiter::tooManyAttempts($sendKey, 1)) {
            throw ValidationException::withMessages(['code' => 'Please wait a minute before requesting another deletion code.']);
        }
        RateLimiter::hit($sendKey, 60);
        $code = (string) random_int(100000, 999999);
        try {
            $user->notify(new AccountDeletionCode($code));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            throw ValidationException::withMessages(['code' => 'We could not send your deletion code. Please wait a minute and try again.']);
        }
        Cache::put($this->key($user, $sessionId), [
            'hash' => Hash::make($code), 'email' => $user->email, 'password_hash' => $user->getAuthPassword(),
        ], now()->addMinutes(10));
    }

    public function verify(User $user, string $sessionId, string $code): void
    {
        Cache::lock('account-deletion:'.$user->id.':lock', 10)->block(3, function () use ($user, $sessionId, $code): void {
            $attemptKey = 'account-deletion:'.$user->id.':attempts';
            if (RateLimiter::tooManyAttempts($attemptKey, 5)) {
                throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again in 10 minutes.']);
            }
            RateLimiter::hit($attemptKey, 600);
            $stored = Cache::get($this->key($user, $sessionId));
            if (! $stored || $stored['email'] !== $user->email || ! hash_equals($stored['password_hash'], $user->getAuthPassword()) || ! Hash::check($code, $stored['hash'])) {
                throw ValidationException::withMessages(['code' => 'The deletion code is incorrect or has expired. Request a new code and try again.']);
            }
            $this->forget($user, $sessionId);
        });
    }

    public function forget(User $user, string $sessionId): void
    {
        Cache::forget($this->key($user, $sessionId));
    }

    private function key(User $user, string $sessionId): string
    {
        return 'account-deletion:'.$user->id.':'.hash('sha256', $sessionId);
    }
}

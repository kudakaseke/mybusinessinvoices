<?php

namespace App;

use App\Models\User;
use App\Notifications\VerifyEmailCode;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class EmailVerificationCodes
{
    public function send(User $user, bool $resend = false): void
    {
        if ($user->hasVerifiedEmail()) {
            return;
        }

        $key = 'email-verification:'.$user->id;
        $existing = Cache::get($key);
        if (! $resend && $existing && $existing['email'] === $user->email) {
            return;
        }

        if (RateLimiter::tooManyAttempts($key.':send', 1)) {
            throw ValidationException::withMessages(['code' => 'Please wait a minute before requesting another code.']);
        }

        $code = (string) random_int(100000, 999999);
        RateLimiter::hit($key.':send', 60);
        try {
            $user->notify(new VerifyEmailCode($code));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            throw ValidationException::withMessages(['code' => 'We could not send your confirmation code. Please wait a minute and request a new code.']);
        }
        Cache::put($key, ['hash' => Hash::make($code), 'email' => $user->email], now()->addMinutes(10));
    }

    public function verify(User $user, string $code): void
    {
        $key = 'email-verification:'.$user->id;
        if (RateLimiter::tooManyAttempts($key.':attempts', 5)) {
            throw ValidationException::withMessages(['code' => 'Too many attempts. Please try again in 10 minutes.']);
        }

        RateLimiter::hit($key.':attempts', 600);
        $stored = Cache::get($key);
        if (! $stored || $stored['email'] !== $user->email || ! Hash::check($code, $stored['hash'])) {
            throw ValidationException::withMessages(['code' => 'The code is incorrect or has expired. Please try again or request a new code.']);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        Cache::forget($key);
        RateLimiter::clear($key.':attempts');
    }
}

<?php

namespace App\Models;

use App\EmailVerificationCodes;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected static function booted(): void
    {
        static::deleting(function (User $user): void {
            if (! Storage::disk('local')->deleteDirectory('receipts/'.$user->id)) {
                throw new \RuntimeException('Unable to remove account receipts.');
            }
            Password::deleteToken($user);
            $user->revokeSessions();
            Cache::forget('email-verification:'.$user->id);
            RateLimiter::clear('email-verification:'.$user->id.':send');
            RateLimiter::clear('email-verification:'.$user->id.':attempts');
        });
    }

    public function revokeSessions(?string $except = null): void
    {
        if (config('session.driver') === 'database') {
            DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))
                ->where('user_id', $this->id)
                ->when($except !== null, fn ($query) => $query->where('id', '!=', $except))->delete();
        }
    }

    public function sendEmailVerificationNotification(): void
    {
        app(EmailVerificationCodes::class)->send($this);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'business_name', 'business_address', 'business_email', 'business_phone',
        'whatsapp_number', 'website', 'default_currencies', 'base_currency',
        'target_currency', 'exchange_rate', 'usd_to_zwg_rate', 'default_tax_rate',
        'business_profile_completed_at', 'payment_reminders_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'payment_reminders_enabled' => 'boolean',
            'password' => 'hashed',
            'default_currencies' => 'array',
            'business_profile_completed_at' => 'datetime',
            'exchange_rate' => 'decimal:6',
            'default_tax_rate' => 'decimal:2',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }
}

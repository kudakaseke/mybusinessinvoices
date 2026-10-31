<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceRefund;
use App\Models\Product;
use App\Models\Quotation;
use App\Observers\RecordActivity;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->isProduction()) {
            config(['app.debug' => false]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([Invoice::class, Quotation::class, InvoicePayment::class, InvoiceRefund::class, CreditNote::class, Expense::class, Client::class, Product::class] as $model) {
            $model::observe(RecordActivity::class);
        }
        RateLimiter::for('outgoing-mail', fn (Request $request): array => [
            Limit::perMinute(6)->by('mail:'.$request->user()->id),
            Limit::perDay(50)->by('daily-mail:'.$request->user()->id),
        ]);
        ResetPassword::createUrlUsing(fn (object $notifiable, string $token): string => rtrim(config('app.url'), '/').route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()], absolute: false));
        foreach (['record', 'note'] as $parameter) {
            Route::bind($parameter, function (string $value): int {
                $identifier = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                abort_if($identifier === false, 404);

                return $identifier;
            });
        }

        RateLimiter::for('application', function (Request $request): array {
            $type = $request->user() ? 'authenticated' : 'guest';
            $actor = $request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'ip:'.$request->ip();
            $limits = [Limit::perMinute((int) config('rate-limits.requests.'.$type))->by('requests:'.$actor)];
            if (! $request->isMethodSafe()) {
                $limits[] = Limit::perMinute((int) config('rate-limits.writes.'.$type))->by('writes:'.$actor);
            }
            if ($request->routeIs('documents.download', 'documents.review', 'credit-notes.download', 'statements.download', 'statements.excel', 'expenses.receipt', 'livewire.preview-file')) {
                $limits[] = Limit::perMinute((int) config('rate-limits.exports'))->by('exports:'.$actor);
            }

            return $limits;
        });
    }
}

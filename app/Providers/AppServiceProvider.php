<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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

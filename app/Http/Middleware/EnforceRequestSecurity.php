<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceRequestSecurity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $hosts = [parse_url(config('app.url'), PHP_URL_HOST)];
        if (app()->environment('local', 'testing')) {
            $hosts = [...$hosts, 'localhost', '127.0.0.1', '[::1]'];
        }
        Request::setTrustedHosts(array_map(fn (string $host): string => '^'.preg_quote($host, '/').'$', array_filter($hosts)));
        $request->getHost();

        $response = $next($request);
        $development = app()->environment('local') ? ' http://localhost:* http://127.0.0.1:*' : '';
        $connections = app()->environment('local') ? $development.' ws://localhost:* ws://127.0.0.1:*' : '';
        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'".$development."; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$development."; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: blob:; connect-src 'self'".$connections."; frame-src 'self' data: blob:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
        }
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}

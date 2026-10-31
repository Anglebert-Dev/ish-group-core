<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Only when the app's own URL is https: a self-hosted copy may run on
        // plain http (and must, to reach the installer on a fresh server).
        $https = app()->environment('production') && str_starts_with((string) config('app.url'), 'https://');

        if ($https) {
            URL::forceScheme('https');

            if (! $request->secure() && ! $request->is('up')) {
                return redirect()->secure($request->getRequestUri());
            }
        }

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($https) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}

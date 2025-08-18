<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Add a set of security-related HTTP response headers.
     *
     * Notes:
     * - HSTS is only enabled when running in production over HTTPS to avoid breaking local HTTP dev.
     * - We intentionally do not add a Content-Security-Policy here since the app currently uses inline
     *   scripts/styles on the display page; adding CSP without nonces/hashes could break the UI.
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = $next($request);

        // Clickjacking protection (allow same origin for embedded views iframes)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // MIME type sniffing protection
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Safer referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restrictive permissions policy (formerly Feature-Policy)
        // Blocks sensitive sensors/features by default.
        $response->headers->set('Permissions-Policy', "camera=(), microphone=(), geolocation=(), interest-cohort=()");

        // Disable legacy XSS Auditor behavior (modern browsers ignore this, set to 0 for consistency)
        $response->headers->set('X-XSS-Protection', '0');

        // Enable HSTS only in production AND when the request is over HTTPS
        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        return $response;
    }
}

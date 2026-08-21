<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking — disallow embedding this app in any frame
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Prevent MIME sniffing — browser must honour the declared Content-Type
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Limit referrer leakage — only origin is sent on cross-origin requests
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Disable browser APIs unused by this application
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        // Internal HQ tool, not a public site — belt-and-braces on top of public/robots.txt
        // in case a bot ignores it or a link leaks outside the department.
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');

        // Content Security Policy. unsafe-inline is required for Tailwind Play CDN and the
        // inline <script> blocks throughout the Blade views (theme anti-flash, Alpine
        // directives). unsafe-eval is required because Livewire bundles Alpine.js, whose
        // x-data/x-init expressions are evaluated via `new Function()`. Sources are locked to
        // self plus the specific CDNs this app actually loads — Tailwind Play CDN, jsDelivr
        // (Quill editor + SweetAlert2), Google Fonts.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);

        // HSTS — only sent over HTTPS to avoid breaking HTTP-only local dev
        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}

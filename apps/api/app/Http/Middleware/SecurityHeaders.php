<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());
        $allowed = [strtolower(parse_url(config('app.url'), PHP_URL_HOST))];
        if (app()->environment(['local', 'testing'])) {
            $allowed = [...$allowed, 'localhost', '127.0.0.1', '[::1]', '::1'];
        }
        if (! in_array($host, $allowed, true)) {
            $response = response()->json(['message' => 'Invalid host.'], 400);
        } elseif ((int) $request->header('Content-Length', 0) > config('security.max_body_bytes')) {
            $response = response()->json(['message' => 'Request too large.'], 413);
        } else {
            $response = $next($request);
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        // Admin currently uses trusted framework inline scripts. Validate a restrictive
        // candidate in staging report-only before considering enforcement.
        $media = implode(' ', array_filter([config('filesystems.disks.r2_published.url')]));
        if (str_contains($response->headers->get('Content-Type', ''), 'text/html')) {
            $response->headers->set('Content-Security-Policy-Report-Only', "default-src 'self'; script-src 'self'; style-src 'self'; style-src-attr 'unsafe-inline'; img-src 'self' data: blob: $media; font-src 'self'; connect-src 'self' blob: $media; media-src 'self' $media; object-src 'none'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'");
        }
        if (app()->environment('production') && config('security.hsts_enabled') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if ($request->is('admin', 'admin/*', 'livewire/*', 'api/*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}

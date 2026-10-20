<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TrustedProxies
{
    public function handle(Request $request, Closure $next)
    {
        // Explicit addresses only. Forwarded Host never controls canonical/admin URLs.
        Request::setTrustedProxies(config('security.trusted_proxies'), Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT);

        return $next($request);
    }
}

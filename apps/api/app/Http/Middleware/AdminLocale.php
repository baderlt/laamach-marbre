<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user() ? $request->user()->admin_locale : $request->cookie('laamach_admin_locale');
        app()->setLocale(in_array($locale, ['fr', 'ar'], true) ? $locale : config('localization.fallback'));

        return $next($request);
    }
}

<?php

use App\Http\Middleware\PreventRequestForgery;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrustedProxies;
use Aws\Exception\AwsException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/admin/login');
        $middleware->web(replace: [Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class => PreventRequestForgery::class]);
        $middleware->replace(TrustProxies::class, TrustedProxies::class);
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*') || $request->expectsJson());
        $storageFailure = static function (Throwable $error): bool {
            for ($cause = $error; $cause; $cause = $cause->getPrevious()) {
                if ($cause instanceof AwsException) {
                    return true;
                }
            }

            return false;
        };
        $exceptions->report(function (Throwable $error) use ($storageFailure) {
            if ($storageFailure($error)) {
                Log::warning('Object storage operation failed; inspect scoped credentials/permissions privately.');

                return false; // Never log SDK requests, signed URLs or credential-bearing exception chains.
            }
        });
        $exceptions->render(function (Throwable $error, Request $request) use ($storageFailure) {
            if ($storageFailure($error)) {
                return $request->expectsJson() || $request->is('api/*') ? response()->json(['message' => 'Media storage is temporarily unavailable. The previous selection was preserved.'], 503) : response('Media storage is temporarily unavailable. Please retry.', 503);
            }
        });
    })->create();

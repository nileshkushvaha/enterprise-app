<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withProviders([
        App\Providers\EventServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('auth.login'));

        $middleware->alias([
            'account.active'      => App\Http\Middleware\EnsureAccountIsActive::class,
            'session.track'       => App\Http\Middleware\TrackUserSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Show a friendly page for expired/invalid verification links
        $exceptions->render(function (InvalidSignatureException $e, Request $request) {
            if ($request->is('auth/verify-email/*')) {
                return response()->view('auth.verification-expired', [], 403);
            }
        });
    })->create();

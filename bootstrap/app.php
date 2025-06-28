<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use App\Http\Middleware\ApiAuthenticate;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => ApiAuthenticate::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle unauthenticated for API
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
        // Handle all other exceptions for API
        $exceptions->render(function (\Throwable $e, $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                $status = ($e instanceof HttpExceptionInterface)
                    ? $e->getStatusCode()
                    : 500;
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: 'Server Error',
                ], $status);
            }
        });
    })
    ->create();



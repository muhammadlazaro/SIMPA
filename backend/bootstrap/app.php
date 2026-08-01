<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware - applied to all requests
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Middleware aliases
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'sanitize' => \App\Http\Middleware\SanitizeInput::class,
            'log.requests' => \App\Http\Middleware\LogRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $safeJson = function (array $payload, int $status) {
            return response()->json($payload, $status);
        };

        // Handle validation exceptions
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) use ($safeJson) {
            // OWASP: Log all input validation failures
            \Log::warning('Validation failed', [
                'path' => $request->path(),
                'method' => $request->method(),
                'errors' => $e->errors(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
            ]);

            return $safeJson([
                'success' => false,
                'message' => 'Terjadi kesalahan validasi',
                'errors' => $e->errors(),
            ], 422);
        });

        // Handle authentication exceptions
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) use ($safeJson) {
            // OWASP: Log attempts to connect with invalid or expired session tokens
            \Log::warning('Authentication failed', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $safeJson([
                'success' => false,
                'message' => 'Anda belum melakukan autentikasi',
            ], 401);
        });

        // Handle authorization exceptions
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) use ($safeJson) {
            // OWASP: Log all access control failures
            \Log::warning('Authorization denied', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
                'user_role' => $request->user()?->getAttribute('role'),
            ]);

            return $safeJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melakukan tindakan ini',
            ], 403);
        });

        // Handle rate-limit exceptions without exposing framework file/trace details.
        $exceptions->renderable(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, $request) use ($safeJson) {
            \Log::warning('Rate limit exceeded', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getKey(),
            ]);

            return $safeJson([
                'success' => false,
                'message' => 'Terlalu banyak percobaan. Silakan coba lagi nanti.',
            ], 429);
        });

        // Handle model not found exceptions
        $exceptions->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e) use ($safeJson) {
            return $safeJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ], 404);
        });

        // Handle route not found exceptions
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) use ($safeJson) {
            return $safeJson([
                'success' => false,
                'message' => 'Endpoint tidak ditemukan',
            ], 404);
        });

        // Reject unsupported HTTP methods without turning scanner probes into 500 responses.
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e) use ($safeJson) {
            return $safeJson([
                'success' => false,
                'message' => 'Metode HTTP tidak diizinkan untuk endpoint ini',
            ], 405);
        });

        // Handle all other exceptions
        $exceptions->renderable(function (\Throwable $e) use ($safeJson) {
            // Log the error
            \Log::error('Application Error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return generic error in production, detailed in development
            if (config('app.debug')) {
                return $safeJson([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'type' => class_basename($e),
                ], 500);
            }

            return $safeJson([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server',
            ], 500);
        });
    })->create();

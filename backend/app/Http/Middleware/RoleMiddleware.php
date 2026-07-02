<?php

namespace App\Http\Middleware;

use App\Http\Helpers\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return ApiResponse::unauthorized('Unauthenticated');
        }

        $userRole = (string) $request->user()?->getAttribute('role');
        $allowedRoles = array_map('trim', $roles);

        if (!in_array($userRole, $allowedRoles, true)) {
            // OWASP: Log all access control failures
            Log::warning('Role access denied', [
                'path' => $request->path(),
                'method' => $request->method(),
                'user_id' => $request->user()?->getKey(),
                'user_role' => $userRole,
                'required_roles' => $allowedRoles,
                'ip' => $request->ip(),
            ]);

            return ApiResponse::forbidden('Akses ditolak. Role tidak sesuai.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class SanitizeInput
{
    /** @var list<string> */
    private const PASSTHROUGH_FIELDS = [
        'password',
        'password_confirmation',
        'token',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Lightweight normalization only; validation/encoding stays contextual.
        $input = $request->all();
        
        array_walk_recursive($input, function (&$value, $key) {
            // Skip file uploads; modifying UploadedFile objects would corrupt the request.
            if ($value instanceof UploadedFile) {
                return;
            }

            if (in_array((string) $key, self::PASSTHROUGH_FIELDS, true)) {
                return;
            }

            if (is_string($value)) {
                // Trim whitespace
                $value = trim($value);
                // Remove null bytes
                $value = str_replace("\0", '', $value);
            }
        });
        
        $request->merge($input);
        
        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request and add security headers
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Enable XSS protection (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Referrer policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        $frontendUrl = (string) config('app.frontend_url', '');
        $appUrl = (string) config('app.url', '');
        $isProduction = (string) config('app.env') === 'production';
        $connectSources = array_values(array_unique(array_filter([
            "'self'",
            $this->cspSource($appUrl),
            $this->cspSource($frontendUrl),
            $isProduction ? null : 'http://localhost:5173',
            $isProduction ? null : 'http://127.0.0.1:5173',
        ])));

        // Content Security Policy (CSP)
        $csp = [
            "default-src 'self'",
            "script-src 'self'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            'connect-src '.implode(' ', $connectSources),
            "object-src 'none'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        
        // Permissions Policy (formerly Feature Policy)
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        
        // HSTS (only in production with HTTPS)
        if ($isProduction) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // OWASP: Disable client-side caching on API responses containing sensitive data
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');

        // OWASP: Remove unnecessary headers revealing framework/runtime information
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }

    private function cspSource(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $source = $parts['scheme'].'://'.$parts['host'];
        if (!empty($parts['port'])) {
            $source .= ':'.$parts['port'];
        }

        return $source;
    }
}

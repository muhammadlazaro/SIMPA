<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogRequests
{
    /** @var list<string> */
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'authorization',
        'cookie',
        'secret',
        'api_key',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log request details
        $startTime = microtime(true);
        
        // Process the request
        $response = $next($request);
        
        // Calculate response time
        $duration = round((microtime(true) - $startTime) * 1000, 2); // in milliseconds
        
        $statusCode = $response->getStatusCode();
        $isError = $statusCode >= 400;

        // Apply sampling for successful requests to keep logs lean.
        if (! $isError && ! $this->shouldLogSuccessfulRequest()) {
            return $response;
        }

        $query = $this->redact((array) $request->query());
        $user = $request->user();

        // Prepare log data
        $logData = [
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $query,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $user?->getKey(),
            'status_code' => $statusCode,
            'duration_ms' => $duration,
        ];
        
        // Log based on status code
        if ($statusCode >= 500) {
            Log::error('API Request - Server Error', $logData);
        } elseif ($statusCode >= 400) {
            Log::warning('API Request - Client Error', $logData);
        } else {
            Log::info('API Request', $logData);
        }
        
        return $response;
    }

    private function shouldLogSuccessfulRequest(): bool
    {
        $sampleRate = (float) config('app.request_log_success_sample_rate', 0.1);
        $sampleRate = max(0.0, min(1.0, $sampleRate));

        if ($sampleRate >= 1.0) {
            return true;
        }
        if ($sampleRate <= 0.0) {
            return false;
        }

        return mt_rand(1, 10_000) <= (int) round($sampleRate * 10_000);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, self::REDACTED_KEYS, true)) {
                $data[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
        // Tambahkan production URL di sini:
        // env('FRONTEND_PRODUCTION_URL', 'https://your-domain.com'),
    ]),

    'allowed_origins_patterns' => [
        // Izinkan port Vite dev (5173-5179) saat development — Vite memilih port fallback jika 5173 sibuk
        '#^http://localhost:517[3-9]$#',
    ],

    'allowed_headers' => [
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'Accept',
        'Origin',
    ],

    'exposed_headers' => [
        'Authorization',
    ],

    'max_age' => 3600, // Cache preflight requests for 1 hour

    'supports_credentials' => true, // Required for Sanctum auth

];


<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Production should only allow the deployed frontend origin. Development
    | keeps Vite fallback ports available so local work remains smooth.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173')))
    ))),

    'allowed_origins_patterns' => env('APP_ENV') === 'production' ? [] : [
        '#^http://localhost:517[3-9]$#',
        '#^http://127\.0\.0\.1:517[3-9]$#',
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

    'max_age' => 3600,

    'supports_credentials' => true,

];

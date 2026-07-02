<?php

return [
    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
        'staging' => [
            'host' => env('DB_STAGING_HOST', 'dbt-dev.bssn.go.id'),
            'port' => env('DB_STAGING_PORT', '3306'),
        ],
        'production' => [
            'host' => env('DB_PRODUCTION_HOST', 'dbt.bssn.go.id'),
            'port' => env('DB_PRODUCTION_PORT', '3306'),
        ],
        'local' => [
            'host' => env('DB_LOCAL_HOST', 'localhost'),
            'port' => env('DB_LOCAL_PORT', '3306'),
        ],
    ],
    'object_storage' => [
        'default_region' => env('MINIO_DEFAULT_REGION', 'us-east-1'),
        'dev' => [
            'endpoint' => env('MINIO_DEV_ENDPOINT', 'https://minio-dev.bssn.go.id:9000'),
            'url' => env('MINIO_DEV_URL', 'https://minio-dev.bssn.go.id:9000'),
        ],
        'production' => [
            'endpoint' => env('MINIO_PROD_ENDPOINT', 'https://minio.bssn.go.id:9000'),
            'url' => env('MINIO_PROD_URL', 'https://minio.bssn.go.id:9000'),
        ],
    ],
    'api_gateway' => [
        'dev' => [
            'base_url' => env('API_GATEWAY_DEV_BASE', 'spl-dev.bssn.go.id'),
        ],
        'production' => [
            'base_url' => env('API_GATEWAY_PROD_BASE', 'spl.bssn.go.id'),
        ],
    ],
    'frontend' => [
        'dev' => [
            'base_url' => env('FRONTEND_DEV_BASE', '/spl-dev.bssn.go.id/'),
        ],
        'production' => [
            'base_url' => env('FRONTEND_PROD_BASE', '/api/'),
        ],
    ],
];

<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;

class EnvironmentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Validate critical environment variables on app boot
        $this->validateEnvironment();
    }

    /**
     * Validate required environment variables
     */
    protected function validateEnvironment(): void
    {
        $required = [
            'APP_NAME',
            'APP_ENV',
            'APP_DEBUG',
            'APP_URL',
            'DB_CONNECTION',
        ];
        
        // Only require APP_KEY if it's not being generated
        if (!str_contains($_SERVER['REQUEST_URI'] ?? '', 'key:generate')) {
            $required[] = 'APP_KEY';
        }

        // For MySQL/PostgreSQL, require additional DB settings
        $dbConnection = env('DB_CONNECTION');
        if ($dbConnection === 'mysql' || $dbConnection === 'pgsql') {
            $required = array_merge($required, [
                'DB_HOST',
                'DB_PORT',
                'DB_DATABASE',
                'DB_USERNAME',
            ]);
        }

        $missing = [];
        foreach ($required as $var) {
            if (empty(env($var))) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                'Missing required environment variables: ' . implode(', ', $missing) .
                '. Please check your .env file.'
            );
        }

        // Validate APP_KEY is set and properly formatted (skip during key generation)
        if (!empty(env('APP_KEY')) && strlen(env('APP_KEY')) < 32) {
            throw new \RuntimeException(
                'APP_KEY must be at least 32 characters. Run: php artisan key:generate'
            );
        }

        // Validate APP_ENV values (allow 'testing' for PHPUnit tests)
        $validEnvironments = ['local', 'development', 'staging', 'production', 'testing'];
        if (!in_array(env('APP_ENV'), $validEnvironments)) {
            throw new \RuntimeException(
                'APP_ENV must be one of: ' . implode(', ', $validEnvironments)
            );
        }

        // Security check: APP_DEBUG should be false in production
        if (env('APP_ENV') === 'production' && env('APP_DEBUG') === true) {
            \Log::warning('⚠️  APP_DEBUG is enabled in production! This is a security risk.');
        }
    }
}


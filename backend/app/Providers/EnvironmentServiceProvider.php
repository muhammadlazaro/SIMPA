<?php

namespace App\Providers;

use App\Exceptions\InvalidEnvironmentConfigurationException;
use Illuminate\Support\ServiceProvider;

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
        $this->validateEnvironment();
    }

    /**
     * Validate required environment-derived configuration values.
     */
    protected function validateEnvironment(): void
    {
        $required = [
            'APP_NAME' => config('app.name'),
            'APP_ENV' => config('app.env'),
            'APP_DEBUG' => config('app.debug'),
            'APP_URL' => config('app.url'),
            'DB_CONNECTION' => config('database.default'),
        ];

        if (! str_contains($_SERVER['REQUEST_URI'] ?? '', 'key:generate')) {
            $required['APP_KEY'] = config('app.key');
        }

        $dbConnection = config('database.default');
        if ($dbConnection === 'mysql' || $dbConnection === 'pgsql') {
            $required['DB_HOST'] = config("database.connections.{$dbConnection}.host");
            $required['DB_PORT'] = config("database.connections.{$dbConnection}.port");
            $required['DB_DATABASE'] = config("database.connections.{$dbConnection}.database");
            $required['DB_USERNAME'] = config("database.connections.{$dbConnection}.username");
        }

        $missing = [];
        foreach ($required as $name => $value) {
            if ($value === null || $value === '') {
                $missing[] = $name;
            }
        }

        if (! empty($missing)) {
            throw new InvalidEnvironmentConfigurationException(
                'Missing required environment variables: ' . implode(', ', $missing) .
                '. Please check your .env file.'
            );
        }

        $appKey = (string) config('app.key');
        if ($appKey !== '' && strlen($appKey) < 32) {
            throw new InvalidEnvironmentConfigurationException(
                'APP_KEY must be at least 32 characters. Run: php artisan key:generate'
            );
        }

        $validEnvironments = ['local', 'development', 'staging', 'production', 'testing'];
        if (! in_array(config('app.env'), $validEnvironments, true)) {
            throw new InvalidEnvironmentConfigurationException(
                'APP_ENV must be one of: ' . implode(', ', $validEnvironments)
            );
        }

        if (config('app.env') === 'production' && config('app.debug') === true) {
            \Log::warning('APP_DEBUG is enabled in production. This is a security risk.');
        }
    }
}

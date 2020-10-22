<?php

namespace Kirschbaum\OpenApiValidator;

use Illuminate\Support\ServiceProvider;

class OpenApiValidatorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/openapi_validator.php' => $this->publishPath('openapi_validator.php'),
        ]);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/openapi_validator.php',
            'openapi_validator'
        );
    }

    private function publishPath($configFile)
    {
        if (function_exists('config_path')) {
            return config_path($configFile);
        } else {
            return base_path('config/' . $configFile);
        }
    }
}

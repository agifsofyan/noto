<?php

namespace Agifsofyan\Noto\Providers;

use Illuminate\Support\ServiceProvider;

class NotoProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerMigrations();
        $this->configurePublishing();
    }

    /**
     * Register Noto migration files.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        return $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    /**
     * Configure publishing for the package.
     *
     * @return void
     */
    protected function configurePublishing()
    {
        if ($this->app->runningInConsole()) {
            
            $this->publishes([
                __DIR__.'/../config/noto.php' => config_path('noto.php'),
              ], 'noto-config');
        }
    }
}
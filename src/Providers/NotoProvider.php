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
        if ($this->app->runningInConsole()) {

            $this->publishes([
              __DIR__.'/../config/noto.php' => config_path('noto.php'),
            ], 'config');

            $this->publishes([__DIR__.'/../database/migrations'], 'migration');
        
          }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/noto.php','noto');
    }
}
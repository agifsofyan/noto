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
        $this->loadMigrationsFrom (__DIR__.'/../database/migrations');
        $this->mergeConfigFrom(__DIR__.'/../config/noto.php','noto');
    }
}
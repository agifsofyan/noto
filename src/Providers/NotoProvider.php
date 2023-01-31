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
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../views', 'noto');
        $this->loadMigrationsFrom (__DIR__.'/../database/migrations');
    }
}